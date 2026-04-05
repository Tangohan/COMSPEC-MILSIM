<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Support\NonDefaultTenantContextGuard;

/**
 * Console modération forum : modérateurs forum OU admins organisation / plateforme.
 */
final class ForumModerationConsoleMiddleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        if (!Session::get('user_id')) {
            Session::flash('error', 'Authentification requise.');

            return Response::redirect(url('login'));
        }
        $ok = function_exists('forum_user_can_moderate') && forum_user_can_moderate();
        if (!$ok) {
            Session::flash('error', 'Accès réservé à la modération.');

            return Response::redirect(url('forum'));
        }

        $blocked = NonDefaultTenantContextGuard::redirectIfInvalid();
        if ($blocked !== null) {
            return $blocked;
        }

        return $next($request);
    }
}
