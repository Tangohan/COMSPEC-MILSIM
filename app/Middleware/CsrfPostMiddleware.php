<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Vérification CSRF sur les requêtes POST (formulaires), avec exceptions pour webhooks et API intégrations.
 */
final class CsrfPostMiddleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        if ($request->method() !== 'POST') {
            return $next($request);
        }
        $path = $request->path();
        if (!str_starts_with($path, '/back-office/')) {
            return $next($request);
        }
        if (str_starts_with($path, '/api/stripe/webhook')
            || str_starts_with($path, '/integrations/')
            || str_starts_with($path, '/calendrier/abonnement/')
        ) {
            return $next($request);
        }
        if (str_starts_with($path, '/api/')) {
            return $next($request);
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect(Session::get('user_id') ? url('dashboard') : url('login'));
        }

        return $next($request);
    }
}
