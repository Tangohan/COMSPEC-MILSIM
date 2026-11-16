<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Sse\SseAccessCodeService;
use App\Support\LoginIntendedDestination;

/**
 * Exige une session SSE active (code redeem), ou une entrée sans code (commandement / membre habilité).
 */
final class SsePortalAccessMiddleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        $svc = new SseAccessCodeService();
        if (!$svc->hasActiveClearance()) {
            // Après « Quitter », on ne rouvre pas le bureau en douce : retour au sas.
            if ($svc->hasLeft()) {
                return Response::redirect(url('atak/sse'));
            }
            if ($svc->canEnterWithoutCode()) {
                $tenantId = (int) Session::get('tenant_id');
                if ($svc->canEnterAsStaff()) {
                    $svc->establishStaffClearance($tenantId);
                } else {
                    $svc->establishMemberClearance($tenantId);
                }
            } else {
                // QR code d'une alerte scanné sans session : on passe par la connexion et on revient sur la page de signalement
                // (retour validé par LoginIntendedDestination : chemin interne uniquement, jamais d'adresse externe).
                if (self::isReportPage($request) && (int) Session::get('user_id') < 1 && LoginIntendedDestination::rememberFromRequest($request)) {
                    return Response::redirect(url('login'));
                }
                // Pas de flash « erreur » : le sas explique déjà qu’un code est requis.
                // Un message d’échec ne s’affiche qu’après une tentative (redeem / CSRF).
                return Response::redirect(url('atak/sse'));
            }
        }

        // Invité : forcer le tenant de session SSE
        if ($svc->isGuest()) {
            $tid = $svc->tenantId();
            if ($tid > 0) {
                Session::set('tenant_id', $tid);
            }
        }

        return $next($request);
    }

    /** Page de signalement d'une alerte (GET /atak/sse/signalement/{jeton}), celle que le QR code ouvre. */
    private static function isReportPage(Request $request): bool
    {
        return strtoupper($request->method()) === 'GET'
            && preg_match('#^/atak/sse/signalement/[A-Za-z0-9_-]{8,64}$#', $request->path()) === 1;
    }
}
