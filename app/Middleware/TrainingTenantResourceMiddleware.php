<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Support\NonDefaultTenantContextGuard;
use App\Support\TrainingLmsStaffAccess;

/**
 * LMS communauté : tenant réel + au moins un droit staff formation (hors administration plateforme seule).
 */
final class TrainingTenantResourceMiddleware
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

        $gate = Gate::getInstance();
        if (!TrainingLmsStaffAccess::allows($gate)) {
            Session::flash('error', 'Accès réservé au pilotage des formations de la communauté.');

            return Response::redirect(url('back-office'));
        }

        return $next($request);
    }
}
