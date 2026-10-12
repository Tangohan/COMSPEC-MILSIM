<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Support\NonDefaultTenantContextGuard;

/**
 * Modules de la communauté active (/back-office/ressources/… : modpacks, ATAK, forum…) :
 * gestionnaire de la communauté (admin.organization / admin.access) ou administrateur plateforme.
 * Ne jamais l’utiliser sous /admin : cet espace est réservé à l’administrateur de la plateforme.
 * Diffère de OrganizationAdminMiddleware (back-office) qui exclut le super-admin sans rôle org.
 */
final class TenantResourceAdminMiddleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        if (!Session::get('user_id')) {
            Session::flash('error', 'Connectez-vous pour continuer.');
            return Response::redirect(url('login'));
        }
        $gate = \App\Core\Gate::getInstance();
        if (!$gate->allows('admin.system') && !$gate->allows('admin.organization') && !$gate->allows('admin.access')) {
            Session::flash('error', 'Accès réservé aux administrateurs.');
            return Response::redirect(url('dashboard'));
        }
        $blocked = NonDefaultTenantContextGuard::redirectIfInvalid();
        if ($blocked !== null) {
            return $blocked;
        }
        return $next($request);
    }
}
