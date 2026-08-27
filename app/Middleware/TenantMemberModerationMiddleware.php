<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Accès aux écrans de restrictions membres au niveau organisation (niveau 0).
 * Ne doit pas être confondu avec la modération « site » (/admin/system/…).
 */
final class TenantMemberModerationMiddleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        if (!Session::get('user_id')) {
            Session::flash('error', 'Connectez-vous pour continuer.');

            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        if (!$gate->allows('admin.members.moderate')) {
            Session::flash('error', 'Cet espace est réservé aux personnes explicitement habilitées à gérer les restrictions d’activité des membres.');

            return Response::redirect(url('back-office'));
        }

        return $next($request);
    }
}
