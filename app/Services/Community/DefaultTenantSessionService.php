<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Core\Session;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Auth\AuthService;

/**
 * Le tenant système (slug `default`) n’est pas un contexte conservable dès qu’une autre communauté existe.
 */
final class DefaultTenantSessionService
{
    public function __construct(
        private UserRepository $userRepository,
        private AuthService $authService,
        private ?TenantRepository $tenantRepository = null,
    ) {}

    public function leaveDefaultIfOtherMembershipsExist(): void
    {
        $email = Session::get('email');
        if ($email === null || $email === '') {
            return;
        }
        // Seule une session posée sur la communauté système (slug « default ») peut être déplacée : ailleurs, la
        // lecture des adhésions par e-mail (faite à chaque requête authentifiée) est inutile. La ligne de la
        // communauté vient du mémo du dépôt partagé, déjà rempli par le contrôle du type de communauté.
        $sessionTid = (int) (Session::get('tenant_id') ?? 0);
        if ($this->tenantRepository !== null && $sessionTid > 0) {
            try {
                $current = $this->tenantRepository->findById($sessionTid);
            } catch (\Throwable) {
                $current = null;
            }
            if (is_array($current) && array_key_exists('slug', $current) && (string) $current['slug'] !== 'default') {
                return;
            }
        }
        $all = $this->userRepository->listTenantsForEmail((string) $email);
        $firstOther = $this->userRepository->firstNonDefaultTenantId($all);
        if ($firstOther === null) {
            return;
        }
        $currentTid = (int) (Session::get('tenant_id') ?? 0);
        $defaultTid = 0;
        foreach ($all as $m) {
            if (($m['slug'] ?? '') === 'default') {
                $defaultTid = (int) $m['tenant_id'];
                break;
            }
        }
        if ($defaultTid < 1 || $currentTid !== $defaultTid) {
            return;
        }
        $this->authService->switchToTenant($firstOther);
    }
}
