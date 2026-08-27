<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Hub lecture / synthèse plateforme : super-administrateurs ou assistance site.
 * Les actions d’écriture restent protégées par {@see SystemAdminMiddleware}.
 */
final class PlatformHubMiddleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        if (!Session::get('user_id')) {
            Session::flash('error', 'Connectez-vous pour continuer.');

            return Response::redirect(url('login'));
        }
        $gate = \App\Core\Gate::getInstance();
        $allowed = $gate->allows('admin.system') || $gate->allows('site.support');
        if (!$allowed) {
            Session::flash('error', 'Accès réservé au pilotage site ou à l’assistance.');
            if ($gate->allows('admin.organization') || $gate->allows('admin.access')) {
                return Response::redirect(url('back-office'));
            }

            return Response::redirect(url('dashboard'));
        }

        return $next($request);
    }
}
