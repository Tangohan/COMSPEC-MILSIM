<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Rbac\RbacService;

class AuthMiddleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        if (!Session::get('user_id')) {
            Session::flash('error', 'Authentification requise.');
            return Response::redirect(url('login'));
        }
        // Recharger les permissions à chaque requête pour que le menu Admin soit affiché
        $roleId = Session::get('role_id');
        $rbac = \App\Core\Container::get(RbacService::class);
        $rbac->setPermissionsForGate($roleId ? (int) $roleId : null);

        $userId = Session::get('user_id');
        $tenantId = Session::get('tenant_id');
        if ($userId && $tenantId) {
            try {
                $mod = \App\Core\Container::get(\App\Services\Moderation\ModerationService::class);
                if ($mod->isAccessBlocked((int) $tenantId, (int) $userId)) {
                    Session::forget('user_id');
                    Session::forget('tenant_id');
                    Session::forget('email');
                    Session::forget('display_name');
                    Session::forget('callsign');
                    Session::forget('role_id');
                    Session::flash('error', 'Votre accès à cette communauté est restreint (sanction active).');

                    return Response::redirect(url('login'));
                }
            } catch (\Throwable) {
            }
        }

        return $next($request);
    }
}
