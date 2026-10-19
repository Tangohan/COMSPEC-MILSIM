<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Core\Gate;
use App\Core\Session;
use App\Repositories\UserRepository;
use App\Services\Rbac\RbacService;

/**
 * Qui peut modifier la vitrine publique d’une communauté directement sur la page.
 *
 * Gestionnaire de la communauté (mêmes droits que la page Présentation du back-office : admin.organization
 * ou admin.access) connecté sur un compte de CETTE communauté, ou administrateur de la plateforme.
 * La route publique /c/{slug} ne passe pas par AuthMiddleware : les droits sont chargés ici.
 */
final class CommunityPageEditorAccess
{
    public function __construct(
        private UserRepository $userRepository,
        private RbacService $rbacService,
    ) {}

    public function canEdit(int $tenantId): bool
    {
        $uid = (int) (Session::get('user_id') ?? 0);
        if ($uid < 1 || $tenantId < 1) {
            return false;
        }
        try {
            $user = $this->userRepository->findById($uid, null);
            if (!is_array($user) || ($user['status'] ?? 'active') !== 'active') {
                return false;
            }
            $this->rbacService->setPermissionsForGateFromUserRow($user, $this->userRepository);
        } catch (\Throwable) {
            return false;
        }
        $gate = Gate::getInstance();
        if ($gate->isPlatformAdmin()) {
            return true;
        }
        if ((int) ($user['tenant_id'] ?? 0) !== $tenantId) {
            return false;
        }

        return $gate->allows('admin.organization') || $gate->allows('admin.access');
    }
}
