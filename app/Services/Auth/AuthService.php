<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Core\Session;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Personnel\SeniorityEnrollmentBootstrapService;

class AuthService
{
    public function __construct(
        private UserRepository $userRepository,
        private TenantRepository $tenantRepository,
        private SeniorityEnrollmentBootstrapService $seniorityEnrollmentBootstrapService,
    ) {}

    public function attempt(int $tenantId, string $email, string $password): bool
    {
        $user = $this->userRepository->findByEmail($tenantId, $email);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }
        if (!empty($user['is_service_account'])) {
            return false;
        }
        if (($user['status'] ?? '') !== 'active') {
            return false;
        }
        $this->loginUser($user);
        return true;
    }

    public function loginUser(array $user): void
    {
        Session::regenerate();
        Session::set('user_id', (int) $user['id']);
        Session::set('tenant_id', (int) $user['tenant_id']);
        Session::set('email', $user['email']);
        Session::set('display_name', $user['display_name'] ?? '');
        Session::set('callsign', $user['callsign'] ?? '');
        Session::set('role_id', $user['role_id'] ? (int) $user['role_id'] : null);
        $this->userRepository->updateLastLogin((int) $user['id']);
        try {
            $this->seniorityEnrollmentBootstrapService->syncTenureCommunityFromEnrollment(
                (int) ($user['tenant_id'] ?? 0),
                (int) $user['id'],
                $user
            );
        } catch (\Throwable) {
            // Ne jamais bloquer une connexion si le référentiel d’ancienneté est partiellement indisponible.
        }
    }

    public function logout(): void
    {
        Session::destroy();
    }

    public function user(): ?array
    {
        $userId = Session::get('user_id');
        if (!$userId) {
            return null;
        }
        $tenantId = Session::get('tenant_id');
        return $this->userRepository->findById((int) $userId, $tenantId ? (int) $tenantId : null);
    }

    public function tenant(): ?array
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return null;
        }
        return $this->tenantRepository->findById((int) $tenantId);
    }

    public function check(): bool
    {
        return Session::get('user_id') !== null;
    }

    /**
     * Bascule la session vers le compte utilisateur du même email dans un autre tenant (multi-communautés).
     */
    public function switchToTenant(int $tenantId): bool
    {
        $email = Session::get('email');
        if ($email === null || $email === '') {
            return false;
        }
        $user = $this->userRepository->findByEmail($tenantId, (string) $email);
        if (!$user || !empty($user['is_service_account']) || ($user['status'] ?? '') !== 'active') {
            return false;
        }
        $this->loginUser($user);
        return true;
    }
}
