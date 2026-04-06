<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

use App\Core\Database;
use App\Repositories\EnlistmentRepository;
use App\Repositories\PasswordResetRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\RoleRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Admin\AdminAuditService;
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

    public function __construct(
        private EnlistmentRepository $enlistmentRepository,
        private UserRepository $userRepository,
        private RoleRepository $roleRepository,
        private TenantRepository $tenantRepository,
        private PersonnelProfileRepository $personnelProfileRepository,
        private PasswordResetRepository $passwordResetRepository,
        private EmailService $emailService,
        private FeatureGateService $featureGateService,
        private AdminAuditService $adminAuditService
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
        if ((int) ($row['submitter_user_id'] ?? 0) > 0) {
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

        $tenantRow = $this->tenantRepository->findById($tenantId);
        $tenantName = trim((string) ($tenantRow['name'] ?? 'Communauté'));
        $reviewUrl = url('back-office/recruitments/' . $enlistmentId);
        $dashboardUrl = url('dashboard');

        $first = trim((string) ($row['first_name'] ?? ''));
        $last = trim((string) ($row['last_name'] ?? ''));
        $fullName = trim($first . ' ' . $last) ?: '—';
        $email = trim((string) ($row['email'] ?? ''));

        $existingSubmitter = (int) ($row['submitter_user_id'] ?? 0);
        if ($existingSubmitter > 0) {
            $this->notifyCandidateAccepted($email, $tenantName, $tenantId, $reviewerComment, $dashboardUrl, 'existing');
            $this->notifyStaffAccepted(
                $tenantId,
                $tenantName,
                $enlistmentId,
                $fullName,
                $email,
                'Le candidat avait déjà un compte lié à la soumission.',
                $reviewUrl
            );

            return ['ok' => true, 'message' => null];
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'ok' => false,
                'message' => 'E-mail de candidature invalide : impossible de créer ou de lier un compte automatiquement.',
            ];
        }

        $existing = $this->userRepository->findByEmail($tenantId, $email);
        if ($existing) {
            $uid = (int) $existing['id'];
            if (!$this->enlistmentRepository->linkSubmitterUserId($tenantId, $enlistmentId, $uid)) {
                return [
                    'ok' => false,
                    'message' => 'Impossible de lier la candidature au compte existant (migration colonnes manquante ?).',
                ];
            }
            $this->notifyCandidateAccepted($email, $tenantName, $tenantId, $reviewerComment, $dashboardUrl, 'existing');
            $this->notifyStaffAccepted(
                $tenantId,
                $tenantName,
                $enlistmentId,
                $fullName,
                $email,
                'Un compte existait déjà pour cet e-mail dans la communauté — la candidature a été rattachée à ce compte.',
                $reviewUrl
            );

            return ['ok' => true, 'message' => null];
        }

        if (!$this->featureGateService->canAddMember($tenantId)) {
            return [
                'ok' => false,
                'message' => 'Limite de membres du plan atteinte : le compte n’a pas été créé. Augmentez le quota ou créez l’utilisateur manuellement.',
            ];
        }

        $memberRoleId = $this->roleRepository->getIdBySlug($tenantId, 'member');
        if (!$memberRoleId || $memberRoleId < 1) {
            return [
                'ok' => false,
                'message' => 'Rôle « member » introuvable pour cette communauté — création du compte annulée.',
            ];
        }

        $pdo = Database::getPdo();
        $userId = 0;
        $rawToken = '';
        $pdo->beginTransaction();
        try {
            $passwordPlaceholder = password_hash(bin2hex(random_bytes(32)), PASSWORD_ARGON2ID);
            $displayName = $fullName !== '—' ? $fullName : null;
            // Statut « active » : l’e-mail est validé côté métier par l’acceptation recrutement ; comptabilisé comme membre
            // (KPI / quotas / « membres actifs ») comme les autres comptes tenant, le mot de passe reste à définir via le lien.
            $userId = $this->userRepository->create($tenantId, [
                'email' => $email,
                'password_hash' => $passwordPlaceholder,
                'display_name' => $displayName,
                'callsign' => null,
                'role_id' => $memberRoleId,
                'grade_id' => null,
                'status' => 'active',
            ]);
            $this->userRepository->syncOrganizationRoles($userId, $tenantId, [$memberRoleId]);
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
            ];
        }

        $this->userRepository->markEmailVerifiedWithoutStatusChange($userId, $tenantId);

        $setupUrl = url('reset-password') . '?token=' . rawurlencode($rawToken);
        $setupSent = $this->emailService->sendTenantUserSetupInvite(
            $email,
            $setupUrl,
            self::SETUP_TOKEN_HOURS,
            $tenantName,
            $tenantId,
            'recruitment_accepted'
        );

        $this->notifyCandidateAccepted($email, $tenantName, $tenantId, $reviewerComment, $dashboardUrl, 'new_password_pending');
        $this->notifyStaffAccepted(
            $tenantId,
            $tenantName,
            $enlistmentId,
            $fullName,
            $email,
            'Un nouveau compte a été créé pour ce candidat (e-mail avec lien de définition du mot de passe).',
            $reviewUrl
        );

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

        return ['ok' => true, 'message' => $warn];
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
