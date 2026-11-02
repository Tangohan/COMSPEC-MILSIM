<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

use App\Controllers\Web\InvitationAcceptController;
use App\Repositories\EnlistmentRepository;
use App\Repositories\EnlistmentTimelineRepository;
use App\Repositories\GradeRepository;
use App\Repositories\InviteLinkRepository;
use App\Repositories\PersonnelAssignmentRepository;
use App\Repositories\RoleRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\UserRepository;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Services\Moderation\IndicatorBlocklistService;
use App\Services\Platform\FeatureGateService;
use App\Services\Rbac\CommunityAccessProfiles;

/**
 * Adhésion par lien d'invitation (action « join ») pour un compte connecté :
 * - sans validation : le compte rejoint la communauté (rôle membre, grade d'entrée ou grade prévu, unité prévue) ;
 * - avec validation : une demande d'adhésion arrive dans la file du recrutement, la décision reste à l'équipe.
 * Chaque usage consomme une utilisation du lien de façon atomique (expiration et plafond vérifiés au même instant).
 */
final class InviteLinkJoinService
{
    public const RESULT_JOINED = 'joined';
    public const RESULT_REQUESTED = 'requested';
    public const RESULT_ALREADY_MEMBER = 'already_member';
    public const RESULT_ALREADY_REQUESTED = 'already_requested';
    public const RESULT_ERROR = 'error';

    public function __construct(
        private InviteLinkRepository $links,
        private UserRepository $users,
        private RoleRepository $roles,
        private FeatureGateService $featureGate,
        private IndicatorBlocklistService $blocklist,
        private UnitRepository $units,
        private PersonnelAssignmentRepository $assignments,
        private EnlistmentRepository $enlistments,
        private EnlistmentTimelineRepository $timeline,
        private UserProfileRepository $profiles,
        private AuditService $audit,
    ) {
    }

    /**
     * @param array<string, mixed> $link ligne hydratée d'InviteLinkRepository
     * @param array<string, mixed> $user compte connecté (id, email, display_name)
     * @return array{result: string, message: string, enlistment_id?: int}
     */
    public function join(array $link, array $user, string $ip): array
    {
        $tenantId = (int) ($link['tenant_id'] ?? 0);
        $linkId = (int) ($link['id'] ?? 0);
        $userId = (int) ($user['id'] ?? 0);
        $email = strtolower(trim((string) ($user['email'] ?? '')));
        if ($tenantId < 1 || $linkId < 1 || $userId < 1 || $email === '') {
            return self::error('Impossible d’utiliser ce lien pour le moment.');
        }
        $status = InviteLinkService::status($link);
        if ($status !== InviteLinkService::STATUS_ACTIVE) {
            return self::error(InviteLinkService::unusableMessage($status));
        }
        if ($this->isMember($email, $tenantId)) {
            return ['result' => self::RESULT_ALREADY_MEMBER, 'message' => 'Vous faites déjà partie de cette communauté.'];
        }
        if ($this->blocklist->isEmailBlockedForTenant($tenantId, $email)
            || ($ip !== '' && $this->blocklist->isIpBlockedForLogin($tenantId, $ip))) {
            return self::error('Ce lien ne peut pas être utilisé avec ce compte pour le moment.');
        }

        if (!empty($link['requires_approval'])) {
            return $this->requestApproval($link, $user, $ip);
        }

        if (!$this->featureGate->canAddMember($tenantId)) {
            return self::error('Cette communauté a atteint le nombre de membres prévu par sa formule. Contactez son équipe.');
        }
        $roleId = (int) ($this->roles->getIdBySlug($tenantId, CommunityAccessProfiles::SLUG_MEMBER) ?? 0);
        if ($roleId < 1) {
            return self::error('Configuration de la communauté incomplète : rôle des membres introuvable.');
        }
        $gradeId = $this->gradeFor($link, $tenantId);

        if (!$this->links->consumeUse($tenantId, $linkId)) {
            return self::error(InviteLinkService::unusableMessage(InviteLinkService::STATUS_EXHAUSTED));
        }
        try {
            $memberId = $this->users->addMembershipToTenant($userId, $tenantId, $roleId, $gradeId > 0 ? $gradeId : null);
        } catch (\Throwable $e) {
            $this->links->releaseUse($tenantId, $linkId);
            $msg = trim($e->getMessage());

            return self::error($e instanceof \RuntimeException && $msg !== '' && !preg_match('/SQLSTATE|PDO/i', $msg)
                ? $msg
                : 'Impossible de rejoindre la communauté pour le moment.');
        }

        $unitId = (int) ($link['metadata']['unit_id'] ?? 0);
        if ($unitId > 0 && $this->units->findById($unitId, $tenantId) !== null) {
            try {
                $this->assignments->syncPrimaryAssignmentFromDossier($memberId, $unitId, 'Membre', 'Lien d’invitation', $tenantId);
            } catch (\Throwable $e) {
                error_log('[invite_link.unit] ' . $e->getMessage());
            }
        }
        try {
            \App\Services\Personnel\NewMemberNumbers::assign($memberId, $tenantId);
            \App\Services\MemberIntegration\MemberIntegrationEntryHook::afterAccountReady(
                $tenantId,
                $memberId,
                $memberId,
                \App\Support\MemberIntegrationCatalog::SOURCE_INVITATION,
                ['role_ids' => [$roleId]]
            );
        } catch (\Throwable $e) {
            error_log('[invite_link.onboarding] ' . $e->getMessage());
        }

        $this->links->recordEvent($tenantId, $linkId, InviteLinkRepository::EVENT_JOIN, $memberId, null, $ip);
        $this->audit->log(AuditAction::INVITE_LINK_JOINED, $tenantId, $memberId, 'invite_link', $linkId, null, (string) ($link['public_code'] ?? ''));

        return ['result' => self::RESULT_JOINED, 'message' => 'Bienvenue dans la communauté.'];
    }

    /**
     * @param array<string, mixed> $link
     * @param array<string, mixed> $user
     * @return array{result: string, message: string, enlistment_id?: int}
     */
    private function requestApproval(array $link, array $user, string $ip): array
    {
        $tenantId = (int) $link['tenant_id'];
        $linkId = (int) $link['id'];
        $userId = (int) $user['id'];
        $email = strtolower(trim((string) ($user['email'] ?? '')));
        if ($this->enlistments->findOngoingSubmittedForAccount($tenantId, $userId, $email) !== null) {
            return ['result' => self::RESULT_ALREADY_REQUESTED, 'message' => 'Votre demande est déjà entre les mains de l’équipe de la communauté.'];
        }
        if (!$this->links->consumeUse($tenantId, $linkId)) {
            return self::error(InviteLinkService::unusableMessage(InviteLinkService::STATUS_EXHAUSTED));
        }
        $profile = $this->profiles->getByUserId($userId) ?? [];
        $first = trim((string) ($profile['first_name'] ?? ''));
        $last = trim((string) ($profile['last_name'] ?? ''));
        if ($first === '' && $last === '') {
            $first = trim((string) ($user['display_name'] ?? '')) ?: '—';
        }
        $label = trim((string) ($link['label'] ?? ''));
        $payload = [
            'first_name' => $first !== '' ? $first : '—',
            'last_name' => $last !== '' ? $last : '—',
            'email' => $email,
            'status' => 'submitted',
            'notes' => 'Demande d’adhésion par le lien d’invitation « ' . ($label !== '' ? $label : (string) $link['public_code']) . ' ».',
            'submitter_user_id' => $userId,
            'submitted_via' => 'account',
            'consent_sharing_at' => date('Y-m-d H:i:s'),
            'shared_fields' => ['share_name' => true, 'share_email' => true, 'mask_email' => false, 'share_callsign' => false, 'form_mode' => 'invite_link'],
        ];
        $openingId = (int) ($link['assign_to_opening_id'] ?? 0);
        if ($openingId > 0) {
            $payload['recruitment_opening_id'] = $openingId;
        }
        try {
            $enlistmentId = $this->enlistments->create($tenantId, $payload);
        } catch (\Throwable $e) {
            $this->links->releaseUse($tenantId, $linkId);
            error_log('[invite_link.request] ' . $e->getMessage());

            return self::error('Votre demande n’a pas pu être enregistrée. Réessayez dans un instant.');
        }
        $this->timeline->append(
            $tenantId,
            $enlistmentId,
            'system',
            'reception',
            'Demande d’adhésion par lien d’invitation',
            $this->requestDetail($link, $tenantId),
            $userId,
            ['invite_code_id' => $linkId]
        );
        $this->timeline->logIntakeFromSubmission($tenantId, $enlistmentId, $userId, 'account');
        $this->links->recordEvent($tenantId, $linkId, InviteLinkRepository::EVENT_REQUEST, $userId, $enlistmentId, $ip);
        $this->audit->log(AuditAction::INVITE_LINK_JOIN_REQUESTED, $tenantId, $userId, 'invite_link', $linkId, null, (string) ($link['public_code'] ?? ''));

        return [
            'result' => self::RESULT_REQUESTED,
            'message' => 'Votre demande d’adhésion est envoyée. L’équipe de la communauté vous répondra depuis le suivi de votre dossier.',
            'enlistment_id' => $enlistmentId,
        ];
    }

    /** @param array<string, mixed> $link */
    private function requestDetail(array $link, int $tenantId): string
    {
        $parts = ['Lien : ' . (string) ($link['label'] ?? $link['public_code'] ?? '')];
        $unitId = (int) ($link['metadata']['unit_id'] ?? 0);
        if ($unitId > 0) {
            $unit = $this->units->findById($unitId, $tenantId);
            if ($unit !== null) {
                $parts[] = 'Unité prévue : ' . (string) ($unit['name'] ?? '');
            }
        }

        return implode(' · ', $parts);
    }

    private function isMember(string $email, int $tenantId): bool
    {
        foreach ($this->users->listTenantsForEmail($email) as $row) {
            if ((int) ($row['tenant_id'] ?? 0) === $tenantId) {
                return true;
            }
        }

        return false;
    }

    /** Grade prévu sur le lien s'il appartient à la communauté, sinon grade d'entrée des recrues. */
    private function gradeFor(array $link, int $tenantId): int
    {
        $gradeId = (int) ($link['metadata']['grade_id'] ?? 0);
        if ($gradeId > 0) {
            try {
                foreach ((new GradeRepository())->listForTenant($tenantId) as $g) {
                    if ((int) ($g['id'] ?? 0) === $gradeId) {
                        return $gradeId;
                    }
                }
            } catch (\Throwable) {
            }
        }

        return InvitationAcceptController::entryGradeIdForTenant($tenantId);
    }

    /** @return array{result: string, message: string} */
    private static function error(string $message): array
    {
        return ['result' => self::RESULT_ERROR, 'message' => $message];
    }
}
