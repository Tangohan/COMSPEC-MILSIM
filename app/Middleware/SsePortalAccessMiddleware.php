<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Sse\SseAccessCodeService;

/**
 * Exige une session SSE active (code redeem), ou une entrée commandement (grant / admin).
 */
final class SsePortalAccessMiddleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        $svc = new SseAccessCodeService();
        if (!$svc->hasActiveClearance()) {
            if ($svc->canEnterAsStaff()) {
                $svc->establishStaffClearance((int) Session::get('tenant_id'));
            } else {
                Session::flash('error', 'Saisissez un code d’accès valide pour entrer dans le portail de renseignement.');

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
}
