<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

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
        $gate = \App\Core\Gate::getInstance();
        $ok = (function_exists('can') && can('forum.moderate'))
            || (function_exists('can') && can('forum.moderate_organization'))
            || $gate->allows('admin.organization')
            || $gate->allows('admin.access');
        if (!$ok) {
            Session::flash('error', 'Accès réservé à la modération.');

            return Response::redirect(url('forum'));
        }

        return $next($request);
    }
}
