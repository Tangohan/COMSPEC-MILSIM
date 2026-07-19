<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Repositories\RoleRepository;
use App\Repositories\TenantCommunityFeedRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserNotificationPreferencesRepository;
use App\Repositories\UserRepository;
use App\Services\Admin\AdminAuditService;
use App\Services\Auth\AuthService;
use App\Services\Email\EmailEvents;
use App\Services\EmailService;

/**
 * Départ volontaire d’une communauté : désactive la ligne users du tenant courant
 * (sans supprimer le compte global / les autres communautés), notifie et bascule la session.
 */
final class LeaveCommunityService
{
    public function __construct(
        private UserRepository $userRepository,
        private TenantRepository $tenantRepository,
        private RoleRepository $roleRepository,
        private AuthService $authService,
        private EmailService $emailService,
        private UserNotificationPreferencesRepository $notificationPreferencesRepository,
        private TenantCommunityFeedRepository $feedRepository,
        private AdminAuditService $adminAuditService,
    ) {}

    /**
     * @return array{ok: bool, error?: string, redirected_to_dashboard?: bool}
     */
    public function leave(int $userId, int $tenantId): array
    {
        if ($userId < 1 || $tenantId < 1) {
            return ['ok' => false, 'error' => 'Session invalide. Reconnectez-vous puis réessayez.'];
        }

        $user = $this->userRepository->findById($userId, $tenantId);
        if (!$user || ($user['status'] ?? '') !== 'active') {
            return ['ok' => false, 'error' => 'Impossible de quitter cette communauté pour le moment.'];
        }

        if (!empty($user['is_service_account'])) {
            return ['ok' => false, 'error' => 'Cette action ne s’applique pas aux comptes techniques.'];
        }

        $tenant = $this->tenantRepository->findById($tenantId);
        if (!$tenant) {
            return ['ok' => false, 'error' => 'Communauté introuvable.'];
        }

        if ($this->isPlaceholderTenant($tenant)) {
            return [
                'ok' => false,
                'error' => 'Vous n’êtes rattaché à aucune communauté pour l’instant. Rien à quitter.',
            ];
        }

        $ownerRoleId = $this->roleRepository->getIdBySlug($tenantId, 'community_owner');
        if ($ownerRoleId !== null && $this->userRepository->userHasTenantRole($userId, $ownerRoleId)) {
            $count = $this->userRepository->countUsersWithRole($ownerRoleId);
            if ($count <= 1) {
                return [
                    'ok' => false,
                    'error' => 'Vous êtes le dernier propriétaire de cette communauté. Transférez ce rôle à un autre membre avant de partir.',
                ];
            }
        }

        $email = strtolower(trim((string) ($user['email'] ?? '')));
        $displayName = trim((string) ($user['display_name'] ?? ''));
        if ($displayName === '') {
            $displayName = $email !== '' ? $email : 'Membre';
        }
        $tenantName = trim((string) ($tenant['name'] ?? 'Communauté'));
        if ($tenantName === '') {
            $tenantName = 'Communauté';
        }

        $this->userRepository->update($userId, $tenantId, ['status' => 'inactive']);

        try {
            $this->adminAuditService->logUserLeftCommunity($tenantId, $userId, $userId);
        } catch (\Throwable) {
            // L’audit ne doit pas bloquer le départ.
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
                $memberLabel . ' a quitté la communauté de son plein gré.',
                null,
                $userId,
                null
            );
        } catch (\Throwable) {
            // Fil optionnel.
        }

        $this->notifyStaff($tenantId, $tenantName, $displayName, $email);
        $this->notifyMember($userId, $email, $displayName, $tenantName);

        $this->switchSessionAfterLeave($email, $userId);

        return ['ok' => true, 'redirected_to_dashboard' => true];
    }

    /** @param array<string, mixed> $tenant */
    private function isPlaceholderTenant(array $tenant): bool
    {
        $slug = strtolower(trim((string) ($tenant['slug'] ?? '')));
        if ($slug === '' || $slug === 'default') {
            return true;
        }
        $name = mb_strtolower(trim((string) ($tenant['name'] ?? '')));
        if ($name === 'aucune organisation' || str_contains($name, 'aucune organisation') || str_contains($name, "pas d'organisation") || str_contains($name, 'pas d’organisation')) {
            return true;
        }

        return false;
    }

    private function notifyStaff(int $tenantId, string $tenantName, string $displayName, string $memberEmail): void
    {
        $recipients = $this->userRepository->listGovernanceEmailsForTenant($tenantId);
        foreach ($recipients as $toRaw) {
            $to = strtolower(trim((string) $toRaw));
            if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            if ($memberEmail !== '' && strcasecmp($to, $memberEmail) === 0) {
                continue;
            }
            $staff = $this->userRepository->findByEmail($tenantId, $to);
            if ($staff
                && !$this->notificationPreferencesRepository->isEmailEventEnabled(
                    (int) ($staff['id'] ?? 0),
                    EmailEvents::MEMBER_LEFT_COMMUNITY_STAFF
                )
            ) {
                continue;
            }
            try {
                $this->emailService->sendMemberLeftCommunityStaff(
                    $to,
                    $tenantName,
                    $displayName,
                    $memberEmail,
                    $tenantId
                );
            } catch (\Throwable) {
                // Continuer les autres destinataires.
            }
        }
    }

    private function notifyMember(int $userId, string $email, string $displayName, string $tenantName): void
    {
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        if (!$this->notificationPreferencesRepository->isEmailEventEnabled($userId, EmailEvents::MEMBER_LEFT_COMMUNITY_CONFIRMATION)) {
            return;
        }
        try {
            $this->emailService->sendMemberLeftCommunityConfirmation(
                $email,
                $displayName,
                $tenantName,
                null
            );
        } catch (\Throwable) {
            // Confirmation optionnelle.
        }
    }

    private function switchSessionAfterLeave(string $email, int $sourceUserId): void
    {
        if ($email === '') {
            $this->authService->logout();

            return;
        }

        $memberships = $this->userRepository->listTenantsForEmail($email);
        $nextNonDefault = $this->userRepository->firstNonDefaultTenantId($memberships);
        if ($nextNonDefault !== null && $this->authService->switchToTenant($nextNonDefault)) {
            return;
        }

        foreach ($memberships as $m) {
            if (($m['slug'] ?? '') === 'default') {
                if ($this->authService->switchToTenant((int) ($m['tenant_id'] ?? 0))) {
                    return;
                }
            }
        }

        $default = $this->tenantRepository->getDefaultTenant();
        if (!$default) {
            $this->authService->logout();

            return;
        }
        $defaultTid = (int) ($default['id'] ?? 0);
        if ($defaultTid < 1) {
            $this->authService->logout();

            return;
        }

        $existing = $this->userRepository->findByEmail($defaultTid, $email);
        if ($existing) {
            $existingId = (int) ($existing['id'] ?? 0);
            if ($existingId > 0 && ($existing['status'] ?? '') !== 'active') {
                $this->userRepository->update($existingId, $defaultTid, ['status' => 'active']);
            }
            if ($this->authService->switchToTenant($defaultTid)) {
                return;
            }
        } else {
            try {
                $roleId = $this->roleRepository->getIdBySlug($defaultTid, 'member') ?? 0;
                $this->userRepository->cloneUserToTenant($sourceUserId, $defaultTid, max(0, $roleId), 0);
                if ($this->authService->switchToTenant($defaultTid)) {
                    return;
                }
            } catch (\Throwable) {
                // Fallback déconnexion.
            }
        }

        $this->authService->logout();
    }
}
