<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

use App\Core\Database;
use App\Repositories\EnlistmentRepository;
use App\Repositories\PasswordResetRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\RoleRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserNotificationPreferencesRepository;
use App\Repositories\UserRepository;
use App\Services\Admin\AdminAuditService;
use App\Services\Email\EmailEvents;
use App\Services\EmailService;
use App\Services\Platform\FeatureGateService;
use DateTimeImmutable;
use Throwable;

/**
 * Après acceptation d’une candidature (statut reviewed) : rattachement ou création de compte tenant + e-mails.
 */
final class EnlistmentAcceptanceProvisioningService
{
    private const SETUP_TOKEN_HOURS = 72;

    /** Rôles restreints à promouvoir en « member » après acceptation recrutement. */
    private const PROMOTABLE_ROLE_SLUGS = ['guest', 'invite'];

    public function __construct(
        private EnlistmentRepository $enlistmentRepository,
        private UserRepository $userRepository,
        private RoleRepository $roleRepository,
        private TenantRepository $tenantRepository,
        private PersonnelProfileRepository $personnelProfileRepository,
        private PasswordResetRepository $passwordResetRepository,
        private EmailService $emailService,
        private FeatureGateService $featureGateService,
        private AdminAuditService $adminAuditService,
        private UserNotificationPreferencesRepository $notificationPreferencesRepository
    ) {}

    /**
     * Vérifie qu’une acceptation est possible avant d’enregistrer la décision (quota, e-mail, etc.).
     * Retourne null si OK, sinon un message d’erreur affichable.
     */
    public function assertAcceptAllowed(int $tenantId, int $enlistmentId): ?string
    {
        $row = $this->enlistmentRepository->findForTenant($tenantId, $enlistmentId);
        if (!$row || (string) ($row['status'] ?? '') !== 'submitted') {
            return 'Cette candidature ne peut pas être acceptée dans son état actuel.';
        }
        $submitter = (int) ($row['submitter_user_id'] ?? 0);
        if ($submitter > 0) {
            $global = $this->userRepository->findById($submitter, null);
            if (!$global) {
                return 'Le compte indiqué sur la candidature est introuvable. Corrigez le dossier ou contactez le support.';
            }
            if ((int) ($global['tenant_id'] ?? 0) === $tenantId) {
                return null;
            }
            $em = trim((string) ($global['email'] ?? ''));
            if ($em !== '' && $this->userRepository->findByEmail($tenantId, $em)) {
                return null;
            }
            if (!$this->featureGateService->canAddMember($tenantId)) {
                return 'Limite de membres du plan atteinte. Augmentez le quota ou refusez temporairement de nouvelles entrées.';
            }

            return null;
        }
        $email = trim((string) ($row['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'E-mail de candidature manquant ou invalide : corrigez le dossier ou créez le compte manuellement.';
        }
        if ($this->userRepository->findByEmail($tenantId, $email)) {
            return null;
        }
        if (!$this->featureGateService->canAddMember($tenantId)) {
            return 'Limite de membres du plan atteinte. Augmentez le quota ou refusez temporairement de nouvelles entrées.';
        }

        return null;
    }

    /**
     * Texte court pour proposer une action manuelle (candidature déjà acceptée).
     */
    public function membershipRepairHint(int $tenantId, array $enlistmentRow): ?string
    {
        if ((string) ($enlistmentRow['status'] ?? '') !== 'reviewed') {
            return null;
        }
        $submitter = (int) ($enlistmentRow['submitter_user_id'] ?? 0);
        $email = trim((string) ($enlistmentRow['email'] ?? ''));

        if ($submitter > 0) {
            $local = $this->userRepository->findById($submitter, $tenantId);
            if (!$local) {
                return 'Le compte lié n’appartient pas à cette communauté ou a été supprimé : vous pouvez finaliser l’adhésion pour recréer le lien.';
            }
            if ($this->roleSlugNeedsPromotion($tenantId, $submitter)) {
                return 'Le compte est encore en accès limité (invité) : finalisez pour lui attribuer le rôle membre.';
            }

            return null;
        }

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $byMail = $this->userRepository->findByEmail($tenantId, $email);
            if ($byMail && $this->roleSlugNeedsPromotion($tenantId, (int) $byMail['id'])) {
                return 'Un compte existe avec le même e-mail mais en accès limité : finalisez pour le passer membre et lier la candidature.';
            }
            if ($byMail) {
                return 'Un compte existe déjà avec cet e-mail dans la communauté : rattachez-le à cette candidature.';
            }

            return 'Aucun compte n’est encore lié à cette candidature acceptée : rattachez la personne pour créer le compte membre.';
        }

        return 'E-mail de candidature manquant : impossible de rattacher automatiquement. Complétez le dossier ou créez le membre à la main.';
    }

    /**
     * @return array{ok: bool, message: string|null}
     */
    public function repairAcceptedMembership(int $tenantId, int $enlistmentId, int $actorUserId): array
    {
        $row = $this->enlistmentRepository->findForTenant($tenantId, $enlistmentId);
        if (!$row || (string) ($row['status'] ?? '') !== 'reviewed') {
            return ['ok' => false, 'message' => 'Seules les candidatures déjà acceptées peuvent être finalisées.'];
        }

        return $this->runMembershipSync($tenantId, $enlistmentId, $row, $actorUserId, null, false);
    }

    /**
     * @return array{ok: bool, message: string|null}
     */
    public function provisionAfterAccept(
        int $tenantId,
        int $enlistmentId,
        int $actorUserId,
        ?string $reviewerComment
    ): array {
        $row = $this->enlistmentRepository->findForTenant($tenantId, $enlistmentId);
        if (!$row || (string) ($row['status'] ?? '') !== 'reviewed') {
            return ['ok' => true, 'message' => null];
        }

        return $this->runMembershipSync($tenantId, $enlistmentId, $row, $actorUserId, $reviewerComment, true);
    }

    /**
     * @return array{ok: bool, message: string|null}
     */
    private function runMembershipSync(
        int $tenantId,
        int $enlistmentId,
        array $row,
        int $actorUserId,
        ?string $reviewerComment,
        bool $sendNotifications
    ): array {
        $tenantRow = $this->tenantRepository->findById($tenantId);
        $tenantName = trim((string) ($tenantRow['name'] ?? 'Communauté'));
        $reviewUrl = url('back-office/recruitments/' . $enlistmentId . '?dossier=1');
        $dashboardUrl = url('dashboard');

        $first = trim((string) ($row['first_name'] ?? ''));
        $last = trim((string) ($row['last_name'] ?? ''));
        $fullName = trim($first . ' ' . $last) ?: '—';
        $email = trim((string) ($row['email'] ?? ''));

        $sync = $this->syncUserIntoTenantForAcceptance($tenantId, $enlistmentId, $row, $actorUserId);
        if (!$sync['ok']) {
            return ['ok' => false, 'message' => $sync['message']];
        }

        $staffLine = (string) $sync['staff_summary'];
        $candidateScenario = (string) $sync['candidate_scenario'];

        if ($sendNotifications) {
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->notifyCandidateAccepted($email, $tenantName, $tenantId, $reviewerComment, $dashboardUrl, $candidateScenario);
            }
            $this->notifyStaffAccepted(
                $tenantId,
                $tenantName,
                $enlistmentId,
                $fullName,
                $email !== '' ? $email : '—',
                $staffLine,
                $reviewUrl
            );
        }

        return ['ok' => true, 'message' => $sync['warn']];
    }

    /**
     * @return array{ok: bool, message: ?string, staff_summary: string, candidate_scenario: string, warn: ?string}
     */
    private function syncUserIntoTenantForAcceptance(int $tenantId, int $enlistmentId, array $row, int $actorUserId): array
    {
        $email = trim((string) ($row['email'] ?? ''));
        $existingSubmitter = (int) ($row['submitter_user_id'] ?? 0);

        if ($existingSubmitter > 0) {
            return $this->syncFromSubmitterUserId($tenantId, $enlistmentId, $existingSubmitter, $email, $actorUserId);
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'ok' => false,
                'message' => 'E-mail de candidature invalide : impossible de créer ou de lier un compte automatiquement.',
                'staff_summary' => '',
                'candidate_scenario' => 'existing',
                'warn' => null,
            ];
        }

        $existing = $this->userRepository->findByEmail($tenantId, $email);
        if ($existing) {
            $uid = (int) $existing['id'];
            if (!$this->enlistmentRepository->linkSubmitterUserId($tenantId, $enlistmentId, $uid)) {
                return [
                    'ok' => false,
                    'message' => 'Impossible de lier la candidature au compte existant.',
                    'staff_summary' => '',
                    'candidate_scenario' => 'existing',
                    'warn' => null,
                ];
            }
            if (!$this->promoteGuestOrInviteToMember($tenantId, $uid)) {
                return [
                    'ok' => false,
                    'message' => 'Le compte existe mais le rôle « membre » est introuvable pour cette communauté.',
                    'staff_summary' => '',
                    'candidate_scenario' => 'existing',
                    'warn' => null,
                ];
            }

            return [
                'ok' => true,
                'message' => null,
                'staff_summary' => 'Compte existant rattaché : le membre dispose désormais du rôle adapté dans la communauté.',
                'candidate_scenario' => 'existing',
                'warn' => null,
            ];
        }

        return $this->createFreshTenantUser($tenantId, $enlistmentId, $row, $email, $actorUserId);
    }

    /**
     * @return array{ok: bool, message: ?string, staff_summary: string, candidate_scenario: string, warn: ?string}
     */
    private function syncFromSubmitterUserId(int $tenantId, int $enlistmentId, int $submitterId, string $enlistmentEmail, int $actorUserId): array
    {
        $global = $this->userRepository->findById($submitterId, null);
        if (!$global) {
            return [
                'ok' => false,
                'message' => 'Compte candidat introuvable.',
                'staff_summary' => '',
                'candidate_scenario' => 'existing',
                'warn' => null,
            ];
        }

        $srcEmail = strtolower(trim((string) ($global['email'] ?? '')));
        $isSealed = !empty($global['deleted_at'])
            || str_ends_with($srcEmail, '@deleted.invalid')
            || (
                (string) ($global['display_name'] ?? '') === \App\Services\Account\AccountDeletionService::DELETED_DISPLAY_NAME
                && (string) ($global['status'] ?? '') === 'inactive'
            );
        if ($isSealed) {
            return [
                'ok' => false,
                'message' => 'Le compte lié à cette candidature a été supprimé. Une nouvelle inscription est nécessaire ; l’historique précédent n’est pas récupérable.',
                'staff_summary' => '',
                'candidate_scenario' => 'existing',
                'warn' => null,
            ];
        }

        $srcTenant = (int) ($global['tenant_id'] ?? 0);
        if ($srcTenant === $tenantId) {
            if (!$this->promoteGuestOrInviteToMember($tenantId, $submitterId)) {
                return [
                    'ok' => false,
                    'message' => 'Rôle « membre » introuvable : impossible de finaliser l’adhésion.',
                    'staff_summary' => '',
                    'candidate_scenario' => 'existing',
                    'warn' => null,
                ];
            }

            return [
                'ok' => true,
                'message' => null,
                'staff_summary' => 'Compte déjà dans la communauté : passage en membre effectué (ou déjà en place).',
                'candidate_scenario' => 'existing',
                'warn' => null,
            ];
        }

        $match = $srcEmail !== '' ? $this->userRepository->findByEmail($tenantId, $srcEmail) : null;
        if ($match) {
            $uid = (int) $match['id'];
            if (!$this->enlistmentRepository->linkSubmitterUserId($tenantId, $enlistmentId, $uid)) {
                return [
                    'ok' => false,
                    'message' => 'Impossible de mettre à jour le lien candidature — compte local déjà présent.',
                    'staff_summary' => '',
                    'candidate_scenario' => 'existing',
                    'warn' => null,
                ];
            }
            if (!$this->promoteGuestOrInviteToMember($tenantId, $uid)) {
                return [
                    'ok' => false,
                    'message' => 'Rôle « membre » introuvable pour finaliser le compte local.',
                    'staff_summary' => '',
                    'candidate_scenario' => 'existing',
                    'warn' => null,
                ];
            }

            return [
                'ok' => true,
                'message' => null,
                'staff_summary' => 'Le compte était rattaché à un autre espace : la candidature pointe maintenant vers le compte de cette communauté et le rôle membre est appliqué.',
                'candidate_scenario' => 'existing',
                'warn' => null,
            ];
        }

        if ($enlistmentEmail !== '' && filter_var($enlistmentEmail, FILTER_VALIDATE_EMAIL)) {
            $byFormEmail = $this->userRepository->findByEmail($tenantId, $enlistmentEmail);
            if ($byFormEmail) {
                $uid = (int) $byFormEmail['id'];
                if (!$this->enlistmentRepository->linkSubmitterUserId($tenantId, $enlistmentId, $uid)) {
                    return [
                        'ok' => false,
                        'message' => 'Impossible de lier la candidature au compte local correspondant à l’e-mail.',
                        'staff_summary' => '',
                        'candidate_scenario' => 'existing',
                        'warn' => null,
                    ];
                }
                if (!$this->promoteGuestOrInviteToMember($tenantId, $uid)) {
                    return [
                        'ok' => false,
                        'message' => 'Rôle « membre » introuvable pour ce compte.',
                        'staff_summary' => '',
                        'candidate_scenario' => 'existing',
                        'warn' => null,
                    ];
                }

                return [
                    'ok' => true,
                    'message' => null,
                    'staff_summary' => 'Compte local retrouvé via l’e-mail de la candidature : lien mis à jour et rôle membre appliqué.',
                    'candidate_scenario' => 'existing',
                    'warn' => null,
                ];
            }
        }

        if (!$this->featureGateService->canAddMember($tenantId)) {
            return [
                'ok' => false,
                'message' => 'Limite de membres du plan atteinte : impossible de dupliquer le compte dans cette communauté.',
                'staff_summary' => '',
                'candidate_scenario' => 'existing',
                'warn' => null,
            ];
        }

        $memberRoleId = $this->roleRepository->getIdBySlug($tenantId, 'member');
        if (!$memberRoleId) {
            return [
                'ok' => false,
                'message' => 'Rôle « membre » introuvable pour cette communauté.',
                'staff_summary' => '',
                'candidate_scenario' => 'existing',
                'warn' => null,
            ];
        }

        try {
            $newId = $this->userRepository->cloneUserToTenant($submitterId, $tenantId, $memberRoleId, null);
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Impossible de créer le compte dans cette communauté : ' . $this->shortExceptionMessage($e),
                'staff_summary' => '',
                'candidate_scenario' => 'existing',
                'warn' => null,
            ];
        }

        if (!$this->enlistmentRepository->linkSubmitterUserId($tenantId, $enlistmentId, $newId)) {
            return [
                'ok' => false,
                'message' => 'Compte créé dans la communauté, mais la candidature n’a pas pu être liée automatiquement.',
                'staff_summary' => '',
                'candidate_scenario' => 'existing',
                'warn' => null,
            ];
        }

        $this->personnelProfileRepository->ensureRecord($newId);
        $this->adminAuditService->logUserCreated($tenantId, $actorUserId, $newId, $srcEmail);

        return [
            'ok' => true,
            'message' => null,
            'staff_summary' => 'Compte dupliqué depuis un autre espace : le membre peut se connecter avec ses identifiants habituels.',
            'candidate_scenario' => 'existing',
            'warn' => null,
        ];
    }

    private function shortExceptionMessage(Throwable $e): string
    {
        $m = trim(preg_replace('/\s+/u', ' ', $e->getMessage()) ?? $e->getMessage());

        return function_exists('mb_substr') ? mb_substr($m, 0, 200) : substr($m, 0, 200);
    }

    /**
     * @return array{ok: bool, message: ?string, staff_summary: string, candidate_scenario: string, warn: ?string}
     */
    private function createFreshTenantUser(int $tenantId, int $enlistmentId, array $row, string $email, int $actorUserId): array
    {
        $email = strtolower(trim($email));

        // Si l’e-mail existe déjà ailleurs : cloner (même mot de passe) plutôt que créer une 2ᵉ identité.
        $global = $this->userRepository->findFirstByEmailGlobal($email);
        if ($global) {
            $srcId = (int) ($global['id'] ?? 0);
            $srcTenant = (int) ($global['tenant_id'] ?? 0);
            if ($srcId > 0 && $srcTenant === $tenantId) {
                if (!$this->enlistmentRepository->linkSubmitterUserId($tenantId, $enlistmentId, $srcId)) {
                    return [
                        'ok' => false,
                        'message' => 'Impossible de lier la candidature au compte existant.',
                        'staff_summary' => '',
                        'candidate_scenario' => 'existing',
                        'warn' => null,
                    ];
                }
                $this->promoteGuestOrInviteToMember($tenantId, $srcId);

                return [
                    'ok' => true,
                    'message' => null,
                    'staff_summary' => 'Compte déjà présent dans la communauté : candidature liée, rôle membre appliqué.',
                    'candidate_scenario' => 'existing',
                    'warn' => null,
                ];
            }
            if ($srcId > 0) {
                return $this->syncFromSubmitterUserId($tenantId, $enlistmentId, $srcId, $email, $actorUserId);
            }
        }

        if (!$this->featureGateService->canAddMember($tenantId)) {
            return [
                'ok' => false,
                'message' => 'Limite de membres du plan atteinte : le compte n’a pas été créé. Augmentez le quota ou créez l’utilisateur manuellement.',
                'staff_summary' => '',
                'candidate_scenario' => 'new_password_pending',
                'warn' => null,
            ];
        }

        $memberRoleId = $this->roleRepository->getIdBySlug($tenantId, 'member');
        if (!$memberRoleId || $memberRoleId < 1) {
            return [
                'ok' => false,
                'message' => 'Rôle « membre » introuvable pour cette communauté — création du compte annulée.',
                'staff_summary' => '',
                'candidate_scenario' => 'new_password_pending',
                'warn' => null,
            ];
        }

        $first = trim((string) ($row['first_name'] ?? ''));
        $last = trim((string) ($row['last_name'] ?? ''));
        $fullName = trim($first . ' ' . $last) ?: '—';

        $pdo = Database::getPdo();
        $userId = 0;
        $rawToken = '';
        $pdo->beginTransaction();
        try {
            $passwordPlaceholder = password_hash(bin2hex(random_bytes(32)), PASSWORD_ARGON2ID);
            $displayName = $fullName !== '—' ? $fullName : null;
            $userId = $this->userRepository->create($tenantId, [
                'email' => $email,
                'password_hash' => $passwordPlaceholder,
                'display_name' => $displayName,
                'callsign' => null,
                'role_id' => $memberRoleId,
                'grade_id' => null,
                'status' => 'active',
            ]);
            $this->userRepository->syncOrganizationRoles($userId, $tenantId, [$memberRoleId], null, true);
            $this->personnelProfileRepository->ensureRecord($userId);

            if (!$this->enlistmentRepository->linkSubmitterUserId($tenantId, $enlistmentId, $userId)) {
                throw new \RuntimeException('link_enlistment_failed');
            }

            $this->passwordResetRepository->deleteExpired();
            $rawToken = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $rawToken);
            $expires = new DateTimeImmutable('+' . self::SETUP_TOKEN_HOURS . ' hours');
            $this->passwordResetRepository->create($userId, $tokenHash, $expires);

            $pdo->commit();
        } catch (Throwable) {
            $pdo->rollBack();

            return [
                'ok' => false,
                'message' => 'Erreur lors de la création du compte. La candidature reste « acceptée » mais vous devez créer l’utilisateur à la main.',
                'staff_summary' => '',
                'candidate_scenario' => 'new_password_pending',
                'warn' => null,
            ];
        }

        $this->userRepository->markEmailVerifiedWithoutStatusChange($userId, $tenantId);

        $tenantRow = $this->tenantRepository->findById($tenantId);
        $tenantName = trim((string) ($tenantRow['name'] ?? 'Communauté'));

        $setupUrl = url('reset-password') . '?token=' . rawurlencode($rawToken);
        $setupSent = false;
        if ($this->notificationPreferencesRepository->isEmailEventEnabled($userId, EmailEvents::TENANT_USER_SETUP)) {
            $setupSent = $this->emailService->sendTenantUserSetupInvite(
                $email,
                $setupUrl,
                self::SETUP_TOKEN_HOURS,
                $tenantName,
                $tenantId,
                'recruitment_accepted'
            );
        }

        $this->adminAuditService->logUserCreated($tenantId, $actorUserId, $userId, $email);

        $warn = null;
        if (!$setupSent) {
            $warn = 'Compte créé, mais l’e-mail avec le lien de mot de passe n’a pas pu être envoyé. Vérifiez la configuration e-mail ou utilisez « mot de passe oublié » pour ce membre.';
            $detail = $this->emailService->getLastSendError();
            if ($detail !== null && $detail !== '') {
                $clean = preg_replace('/\s+/u', ' ', $detail) ?? $detail;
                $warn .= ' ' . (function_exists('mb_substr') ? mb_substr($clean, 0, 200) : substr($clean, 0, 200));
            }
        }

        return [
            'ok' => true,
            'message' => null,
            'staff_summary' => 'Un nouveau compte a été créé pour ce candidat (e-mail avec lien de définition du mot de passe).',
            'candidate_scenario' => 'new_password_pending',
            'warn' => $warn,
        ];
    }

    private function roleSlugNeedsPromotion(int $tenantId, int $userId): bool
    {
        $u = $this->userRepository->findById($userId, $tenantId);
        if (!$u) {
            return true;
        }
        $slug = strtolower(trim((string) ($this->userRepository->getRoleSlugForUser($userId) ?? '')));

        return $slug !== '' && in_array($slug, self::PROMOTABLE_ROLE_SLUGS, true);
    }

    private function promoteGuestOrInviteToMember(int $tenantId, int $userId): bool
    {
        $user = $this->userRepository->findById($userId, $tenantId);
        if (!$user) {
            return false;
        }
        $slug = strtolower(trim((string) ($this->userRepository->getRoleSlugForUser($userId) ?? '')));
        if ($slug === '' || !in_array($slug, self::PROMOTABLE_ROLE_SLUGS, true)) {
            return true;
        }
        $memberRoleId = $this->roleRepository->getIdBySlug($tenantId, 'member');
        if (!$memberRoleId) {
            return false;
        }
        $this->userRepository->syncOrganizationRoles($userId, $tenantId, [$memberRoleId], null, true);
        $this->personnelProfileRepository->ensureRecord($userId);
        $this->userRepository->markEmailVerifiedWithoutStatusChange($userId, $tenantId);

        return true;
    }

    private function staffEmails(int $tenantId): array
    {
        $recipients = $this->userRepository->listRecruitmentNotificationEmailsForTenant($tenantId);
        if ($recipients === []) {
            $recipients = $this->userRepository->listGovernanceEmailsForTenant($tenantId);
        }

        return $recipients;
    }

    /**
     * @param 'existing'|'new_password_pending' $accountScenario
     */
    private function notifyCandidateAccepted(
        string $email,
        string $tenantName,
        int $tenantId,
        ?string $reviewerComment,
        string $dashboardUrl,
        string $accountScenario
    ): void {
        try {
            $em = strtolower(trim($email));
            $u = $em !== '' ? $this->userRepository->findByEmail($tenantId, $em) : null;
            if ($u && !$this->notificationPreferencesRepository->isEmailEventEnabled((int) ($u['id'] ?? 0), EmailEvents::ENLISTMENT_ACCEPTED_CANDIDATE)) {
                return;
            }
            $this->emailService->sendEnlistmentAcceptedCandidate(
                $email,
                $tenantName,
                $reviewerComment,
                $dashboardUrl,
                $accountScenario,
                $tenantId
            );
        } catch (Throwable) {
        }
    }

    private function notifyStaffAccepted(
        int $tenantId,
        string $tenantName,
        int $enlistmentId,
        string $candidateFullName,
        string $candidateEmail,
        string $summaryLine,
        string $reviewUrl
    ): void {
        foreach ($this->staffEmails($tenantId) as $to) {
            try {
                $em = strtolower(trim($to));
                $u = $em !== '' ? $this->userRepository->findByEmail($tenantId, $em) : null;
                if ($u && !$this->notificationPreferencesRepository->isEmailEventEnabled((int) ($u['id'] ?? 0), EmailEvents::ENLISTMENT_ACCEPTED_STAFF)) {
                    continue;
                }
                $this->emailService->sendEnlistmentAcceptedStaff(
                    $to,
                    $tenantName,
                    $enlistmentId,
                    $candidateFullName,
                    $candidateEmail,
                    $summaryLine,
                    $reviewUrl,
                    $tenantId
                );
            } catch (Throwable) {
            }
        }
    }
}
