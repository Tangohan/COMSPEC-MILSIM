<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\UserRepository;
use App\Services\Rbac\RbacService;

class AuthMiddleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        if (!Session::get('user_id')) {
            Session::flash('error', 'Authentification requise.');
            return Response::redirect(url('login'));
        }

        Container::get(\App\Services\Community\DefaultTenantSessionService::class)->leaveDefaultIfOtherMembershipsExist();

        $userId = (int) Session::get('user_id');
        $userRepo = Container::get(UserRepository::class);
        $user = $userRepo->findById($userId, null);
        if ($user === null) {
            $this->clearAuthSession();
            Session::flash('error', 'Ce compte n’existe plus ou la session est invalide.');

            return Response::redirect(url('login'));
        }
        if (($user['status'] ?? '') !== 'active') {
            $this->clearAuthSession();
            Session::flash('error', 'Ce compte n’est plus actif.');

            return Response::redirect(url('login'));
        }
        $sessionTenantId = Session::get('tenant_id');
        if ($sessionTenantId === null || (int) $sessionTenantId !== (int) $user['tenant_id']) {
            $this->clearAuthSession();
            Session::flash('error', 'Session invalide. Merci de vous reconnecter.');

            return Response::redirect(url('login'));
        }

        // Recharger les permissions à chaque requête pour que le menu Admin soit affiché
        $roleId = Session::get('role_id');
        $email = Session::get('email');
        $rbac = Container::get(RbacService::class);
        $rbac->setPermissionsForGate($roleId ? (int) $roleId : null, $email !== null && $email !== '' ? (string) $email : null);

        $tenantId = (int) $user['tenant_id'];
        try {
            $mod = Container::get(\App\Services\Moderation\ModerationService::class);
            if ($mod->isAccessBlocked($tenantId, $userId)) {
                $this->clearAuthSession();
                Session::flash('error', 'Votre accès à cette communauté est restreint (sanction active).');

                return Response::redirect(url('login'));
            }
        } catch (\Throwable) {
            $this->clearAuthSession();
            Session::flash('error', 'Impossible de vérifier votre accès. Réessayez plus tard.');

            return Response::redirect(url('login'));
        }

        return $next($request);
    }

    private function clearAuthSession(): void
    {
        Session::forget('user_id');
        Session::forget('tenant_id');
        Session::forget('email');
        Session::forget('display_name');
        Session::forget('callsign');
        Session::forget('role_id');
    }
}
