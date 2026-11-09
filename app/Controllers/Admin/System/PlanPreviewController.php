<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SubscriptionPlanRepository;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Services\Platform\EffectivePlan;

/**
 * POST /admin/apercu-formule et /admin/apercu-formule/quitter : « Aperçu de la formule » (administrateur de la plateforme).
 * Stocké en session seulement, pour la communauté en cours ; ne change ni la formule enregistrée, ni les données, ni les droits.
 */
final class PlanPreviewController
{
    public function __construct(private ?SubscriptionPlanRepository $plans = null)
    {
        $this->plans ??= Container::get(SubscriptionPlanRepository::class);
    }

    public function start(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée, réessayez.');

            return $this->back($request);
        }
        $tenantId = (int) Session::get('tenant_id', 0);
        $slug = strtolower(trim((string) $request->input('plan_slug', '')));
        if ($slug === '') {
            return $this->stop($request, $params);
        }
        $plan = $tenantId > 0 && preg_match('/^[a-z0-9_\-]{1,64}$/', $slug) === 1 ? $this->plans->findBySlug($slug) : null;
        if ($plan === null) {
            Session::flash('error', 'Formule inconnue : aperçu non activé.');

            return $this->back($request);
        }
        $name = trim((string) ($plan['name'] ?? '')) ?: $slug;
        EffectivePlan::start($slug, $name, $tenantId);
        AuditService::logPlatformAction(AuditAction::PLATFORM_PLAN_PREVIEW_STARTED, ['formule' => $slug, 'nom' => $name], $tenantId);
        Session::flash('success', 'Aperçu de la formule ' . $name . ' : lecture seule, rien n’est modifié.');

        return $this->back($request);
    }

    public function stop(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée, réessayez.');

            return $this->back($request);
        }
        $current = EffectivePlan::current();
        EffectivePlan::stop();
        if ($current !== null) {
            AuditService::logPlatformAction(AuditAction::PLATFORM_PLAN_PREVIEW_STOPPED, ['formule' => $current['slug']], $current['tenant_id']);
            Session::flash('success', 'Aperçu terminé : formule réelle de la communauté.');
        }

        return $this->back($request);
    }

    /** Retour à la page d'où vient la demande (même site), sinon l'accueil du back-office. */
    private function back(Request $request): Response
    {
        $ref = $request->header('Referer');
        $host = preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? ''));
        if (is_string($ref) && $host !== '' && (string) parse_url($ref, PHP_URL_HOST) === $host) {
            return Response::redirect($ref);
        }

        return Response::redirect(url('back-office'));
    }
}
