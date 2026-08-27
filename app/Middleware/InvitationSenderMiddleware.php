<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Support\NonDefaultTenantContextGuard;

/**
 * Invitations : administrateurs organisation OU permission invitations.send (recruteurs, fondateurs).
 */
final class InvitationSenderMiddleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        if (!Session::get('user_id')) {
            Session::flash('error', 'Connectez-vous pour continuer.');
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        if ($gate->allows('admin.organization') || $gate->allows('admin.access') || $gate->allows('invitations.send')) {
            $blocked = NonDefaultTenantContextGuard::redirectIfInvalid();
            if ($blocked !== null) {
                return $blocked;
            }
            return $next($request);
        }
        Session::flash('error', 'Accès réservé aux personnes autorisées à envoyer des invitations.');
        return Response::redirect(url('dashboard'));
    }
}
