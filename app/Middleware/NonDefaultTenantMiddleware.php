<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Support\NonDefaultTenantContextGuard;

/**
 * Contexte communauté réelle (hors tenant default) sans exiger les permissions back-office.
 * Utile pour LMS admin, etc.
 */
final class NonDefaultTenantMiddleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        if (!Session::get('user_id')) {
            Session::flash('error', 'Connectez-vous pour continuer.');
            return Response::redirect(url('login'));
        }
        $blocked = NonDefaultTenantContextGuard::redirectIfInvalid();
        if ($blocked !== null) {
            return $blocked;
        }
        return $next($request);
    }
}
