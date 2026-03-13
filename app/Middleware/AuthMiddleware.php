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
        return $next($request);
    }
}
