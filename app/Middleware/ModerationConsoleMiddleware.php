<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Support\NonDefaultTenantContextGuard;

/**
 * Console de modération (signalements, contenus en attente) : modérateurs OU admins organisation / plateforme.
 */
final class ModerationConsoleMiddleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        if (!Session::get('user_id')) {
            Session::flash('error', 'Connectez-vous pour continuer.');

            return Response::redirect(url('login'));
        }
        $ok = moderation_user_can_moderate();
        if (!$ok) {
            Session::flash('error', 'Accès réservé à la modération.');

            return Response::redirect(url('dashboard'));
        }

        $blocked = NonDefaultTenantContextGuard::redirectIfInvalid();
        if ($blocked !== null) {
            return $blocked;
        }

        return $next($request);
    }
}
