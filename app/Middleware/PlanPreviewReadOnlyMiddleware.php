<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Platform\EffectivePlan;
use App\Services\Platform\EffectiveTenantType;

/**
 * Aperçu de la formule : lecture seule. Toute requête qui modifie quelque chose (POST, PUT, PATCH, DELETE : actions,
 * envois, paiements) est refusée tant que l'aperçu est actif, sauf pour quitter ou changer l'aperçu et se déconnecter.
 * Ne dépend que de la session : l'aperçu ne peut que restreindre.
 */
final class PlanPreviewReadOnlyMiddleware
{
    /** Chemins (POST) toujours permis pendant l'aperçu. */
    private const ALLOWED_PATHS = [
        '/admin/apercu-formule',
        '/admin/apercu-formule/quitter',
        '/admin/apercu-formule/vue',
        '/logout',
        '/admin/system/tenant-intervention/leave',
    ];

    public function __invoke(Request $request, callable $next): Response
    {
        if (in_array(strtoupper($request->method()), ['GET', 'HEAD', 'OPTIONS'], true)
            || (!EffectivePlan::sessionMatches() && !EffectiveTenantType::sessionMatches())
            || in_array(rtrim($request->path(), '/'), self::ALLOWED_PATHS, true)) {
            return $next($request);
        }

        $message = EffectivePlan::sessionMatches()
            ? 'Aperçu de la formule ' . EffectivePlan::sessionName() . ' : lecture seule, aucune action n’est enregistrée. Quittez l’aperçu pour agir.'
            : 'Aperçu de la vue ' . EffectiveTenantType::sessionLabel() . ' : lecture seule, aucune action n’est enregistrée. Quittez l’aperçu pour agir.';
        if (!$request->isBrowserNavigation() || str_starts_with($request->path(), '/api/')) {
            return Response::json(['ok' => false, 'error' => $message, 'plan_preview_read_only' => true], 403);
        }
        Session::flash('error', $message);
        $back = $request->header('Referer');
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $target = is_string($back) && $host !== '' && (string) parse_url($back, PHP_URL_HOST) === preg_replace('/:\d+$/', '', $host)
            ? $back
            : url('back-office');

        return Response::redirect($target);
    }
}
