<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Accès aux écrans missions inter-unités : hors simple membre, sans exiger tout le périmètre « ressources admin ».
 */
final class InterteamMissionsAccessMiddleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        if (!Session::get('user_id')) {
            Session::flash('error', 'Authentification requise.');

            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        $ok = $gate->allows('admin.system')
            || $gate->allows('admin.organization')
            || $gate->allows('admin.access')
            || (function_exists('can') && can('interteam.missions.manage'))
            || (function_exists('can') && can('interteam.missions.respond'));
        if (!$ok) {
            Session::flash('error', 'Accès réservé aux personnes habilitées pour les missions inter-unités.');

            return Response::redirect(url('dashboard'));
        }

        return $next($request);
    }
}
