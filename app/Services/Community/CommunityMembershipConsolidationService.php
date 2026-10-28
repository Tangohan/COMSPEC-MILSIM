<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Repositories\RoleRepository;
use App\Repositories\TenantCommunityFeedRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Admin\AdminAuditService;
use App\Support\PortalAccessChoice;

/**
 * Un compte = une communauté réelle.
 * À la connexion, si plusieurs appartenances actives restent, l’utilisateur
 * choisit sa communauté définitive ; les autres sont quittées.
 */
final class CommunityMembershipConsolidationService
{
    public function __construct(
        private UserRepository $userRepository,
        private TenantRepository $tenantRepository,
        private RoleRepository $roleRepository,
        private AdminAuditService $adminAuditService,
        private TenantCommunityFeedRepository $feedRepository,
        private AtakCommunityMigrationService $atakCommunityMigrationService,
    ) {}

    /**
     * @param array{tenant_id: int, user_id: int, tenant_name?: string, tenant_slug?: string} $chosen
     * @param list<array{tenant_id: int, user_id: int, tenant_name?: string, tenant_slug?: string}> $candidates
     * @return array{ok: bool, error?: string, left_count?: int, atak?: array{ok: bool, steam_moved: bool, sessions_revoked: int, terminals_moved: int}}
     */
    public function consolidateToChoice(array $chosen, array $candidates): array
    {
        $keepTenantId = (int) ($chosen['tenant_id'] ?? 0);
        $keepUserId = (int) ($chosen['user_id'] ?? 0);
        if ($keepTenantId < 1 || $keepUserId < 1) {
            return ['ok' => false, 'error' => 'Choix de communauté invalide.'];
        }

        $toLeave = [];
        foreach ($candidates as $candidate) {
            $tenantId = (int) ($candidate['tenant_id'] ?? 0);
            $userId = (int) ($candidate['user_id'] ?? 0);
            if ($tenantId < 1 || $userId < 1) {
                continue;
            }
            if ($tenantId === $keepTenantId && $userId === $keepUserId) {
                continue;
            }
            if (PortalAccessChoice::membershipIsPlaceholder($candidate)) {
                continue;
            }
            $toLeave[] = $candidate;
        }

        foreach ($toLeave as $candidate) {
            $block = $this->lastOwnerBlock(
                (int) $candidate['user_id'],
                (int) $candidate['tenant_id'],
                trim((string) ($candidate['tenant_name'] ?? ''))
            );
            if ($block !== null) {
                return ['ok' => false, 'error' => $block];
            }
        }

        $leftCount = 0;
        foreach ($toLeave as $candidate) {
            $userId = (int) $candidate['user_id'];
            $tenantId = (int) $candidate['tenant_id'];
            $tenantName = trim((string) ($candidate['tenant_name'] ?? ''));
            if ($tenantName === '') {
                $tenant = $this->tenantRepository->findById($tenantId);
                $tenantName = trim((string) ($tenant['name'] ?? 'Communauté'));
            }

            $this->userRepository->leaveCommunity($userId, $tenantId);
            $leftCount++;

            try {
                $this->adminAuditService->logUserLeftCommunity($tenantId, $userId, $userId);
            } catch (\Throwable) {
            }

            $user = $this->userRepository->findById($userId, $keepTenantId)
                ?? $this->userRepository->findById($userId, null);
            $displayName = trim((string) ($user['display_name'] ?? ''));
            $email = strtolower(trim((string) ($user['email'] ?? '')));
            if ($displayName === '') {
                $displayName = $email !== '' ? $email : 'Membre';
            }
            $memberLabel = $displayName;
            if ($email !== '' && strcasecmp($displayName, $email) !== 0) {
                $memberLabel .= ' (' . $email . ')';
            }

            try {
                $this->feedRepository->insert(
                    $tenantId,
                    'member_left',
                    'Départ d’un membre',
                    $memberLabel . ' a quitté la communauté (choix définitif à la connexion).',
                    null,
                    $userId,
                    null
                );
            } catch (\Throwable) {
            }
        }

        // Même compte : basculer aussi les sas « pas d’organisation » restants.
        try {
            $this->userRepository->leaveOtherLiveCommunities($keepUserId, $keepTenantId);
        } catch (\Throwable) {
        }

        $atak = ['ok' => true, 'steam_moved' => false, 'sessions_revoked' => 0, 'terminals_moved' => 0];
        try {
            $atak = $this->atakCommunityMigrationService->migrateToDefinitiveCommunity(
                $keepUserId,
                $keepTenantId,
                $toLeave
            );
        } catch (\Throwable $e) {
            error_log('[community.consolidate.atak] ' . $e->getMessage());
        }

        return ['ok' => true, 'left_count' => $leftCount, 'atak' => $atak];
    }

    private function lastOwnerBlock(int $userId, int $tenantId, string $tenantName): ?string
    {
        if ($userId < 1 || $tenantId < 1) {
            return null;
        }
        $ownerRoleId = $this->roleRepository->getIdBySlug($tenantId, 'community_owner');
        if ($ownerRoleId === null) {
            return null;
        }
        if (!$this->userRepository->userHasTenantRole($userId, $ownerRoleId)) {
            return null;
        }
        if ($this->userRepository->countUsersWithRole($ownerRoleId) > 1) {
            return null;
        }
        $label = $tenantName !== '' ? $tenantName : 'cette communauté';

        return 'Vous êtes le dernier propriétaire de « ' . $label
            . ' ». Ouvrez temporairement cette communauté, transférez le rôle propriétaire à un autre membre, puis reconnectez-vous pour finaliser votre choix.';
    }
}
