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
use App\Services\Platform\EffectiveTenantType;
use App\Services\Community\TenantTypeConfig;
use App\Repositories\TenantRepository;

/**
 * POST /admin/apercu-formule et /admin/apercu-formule/quitter : « Aperçu de la formule » (administrateur de la plateforme).
 * Stocké en session seulement, pour la communauté en cours ; ne change ni la formule enregistrée, ni les données, ni les droits.
 */
final class PlanPreviewController
{
    public function __construct(private ?SubscriptionPlanRepository $plans = null, private ?TenantRepository $tenants = null)
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

    /** POST /admin/apercu-formule/vue : « Aperçu de la vue » (Complet, Bureau des effectifs, Carte ATAK). Ne fait que restreindre. */
    public function startView(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée, réessayez.');

            return $this->back($request);
        }
        $tenantId = (int) Session::get('tenant_id', 0);
        $type = strtolower(trim((string) $request->input('view_type', '')));
        $current = EffectiveTenantType::current();
        if ($type === '') {
            return $this->stopView($current);
        }
        $tenant = $tenantId > 0 && TenantTypeConfig::isValidType($type) ? ($this->tenants ??= Container::get(TenantRepository::class))->findById($tenantId) : null;
        $real = $tenant !== null ? TenantTypeConfig::normalizeType((string) ($tenant['tenant_type'] ?? 'full')) : null;
        if ($real === null || !in_array($type, EffectiveTenantType::previewable($real), true)) {
            Session::flash('error', 'Vue indisponible pour cette communauté : aperçu non activé.');

            return $this->back($request);
        }
        if ($type === $real) {
            return $this->stopView($current);
        }
        EffectiveTenantType::start($type, $tenantId);
        $label = TenantTypeConfig::label($type);
        AuditService::logPlatformAction(AuditAction::PLATFORM_VIEW_PREVIEW_STARTED, ['vue' => $type, 'nom' => $label, 'type_reel' => $real], $tenantId);
        Session::flash('success', 'Aperçu de la vue ' . $label . ' : lecture seule, rien n’est modifié.');

        return $this->back($request);
    }

    /** @param array{type: string, label: string, tenant_id: int}|null $current */
    private function stopView(?array $current): Response
    {
        EffectiveTenantType::stop();
        if ($current !== null) {
            AuditService::logPlatformAction(AuditAction::PLATFORM_VIEW_PREVIEW_STOPPED, ['vue' => $current['type']], $current['tenant_id']);
            Session::flash('success', 'Aperçu terminé : vue réelle de la communauté.');
        }

        return Response::redirect(url('back-office'));
    }

    public function stop(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée, réessayez.');

            return $this->back($request);
        }
        $view = EffectiveTenantType::current();
        EffectiveTenantType::stop();
        if ($view !== null) {
            AuditService::logPlatformAction(AuditAction::PLATFORM_VIEW_PREVIEW_STOPPED, ['vue' => $view['type']], $view['tenant_id']);
            Session::flash('success', 'Aperçu terminé : vue réelle de la communauté.');
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
