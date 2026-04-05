<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Core\Session;
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
    ) {}

    public function leaveDefaultIfOtherMembershipsExist(): void
    {
        $email = Session::get('email');
        if ($email === null || $email === '') {
            return;
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
