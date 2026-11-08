<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PlatformAbuseAlertRepository;
use App\Repositories\PlatformLoadRepository;
use App\Repositories\PlatformSettingsRepository;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Services\Watch\LoadLevel;
use App\Services\Watch\LoadShedPolicy;
use App\Services\Watch\PlanRankIndex;
use App\Services\Watch\WatchFlusher;
use App\Services\Watch\WatchSettings;
use App\Services\Watch\WatchState;
use App\Support\PlanGift;

/**
 * /admin/system/surcharge : charge de l'application (requêtes par route et par communauté, erreurs, tendance), seuils
 * d'alerte et priorité sous charge. Administrateur de la plateforme seulement (SystemAdminMiddleware sur les routes).
 */
final class SystemLoadController
{
    /** Réglages de ce formulaire ; ceux des détecteurs du mod se règlent sur leur page. */
    private const FORM_KEYS = [
        WatchSettings::SHED_ENABLED, WatchSettings::SHED_PERCENT, WatchSettings::ALERT_P95_MS,
        WatchSettings::ALERT_ERROR_PCT, WatchSettings::ALERT_LOAD_PER_CORE,
    ];

    public function index(Request $request, array $params = []): Response
    {
        $now = time();
        $repo = new PlatformLoadRepository();
        $state = WatchState::raw();
        $alerts = new PlatformAbuseAlertRepository();
        $tenants = $repo->byTenant(1, $now, 20);
        foreach ($tenants as &$t) {
            $slug = is_array($t['tenant'] ?? null) && $t['tenant'] !== [] ? PlanGift::effectivePlanSlug($t['tenant']) : 'free';
            $t['plan_slug'] = $slug;
            $t['plan_label'] = self::planLabel($slug);
            unset($t['tenant']);
        }
        unset($t);

        return Response::view('layout.main', [
            'title' => 'Charge et surcharge',
            'content' => 'admin.system.load',
            'isPlatformAdminShell' => true,
            'backOfficePageCss' => ['platform-admin.css'],
            'loadNow' => $now,
            'loadState' => $state,
            'loadMeasuredLevel' => WatchState::measuredLevel($state, $now),
            'loadEffectiveLevel' => WatchState::effectiveLevel($state, $now),
            'loadStateAge' => WatchState::ageSeconds($state, $now),
            'loadSettings' => WatchSettings::load(),
            'loadMinutes' => $repo->minuteSeries(60, $now),
            'loadHours' => $repo->hourSeries(24, $now),
            'loadRoutes' => $repo->topRoutes(15, $now, 15),
            'loadTenants' => $tenants,
            'loadAlerts' => $alerts->recent('', PlatformAbuseAlertRepository::KIND_LOAD, 10),
            'loadPlanIndexAge' => PlanRankIndex::ageSeconds($now),
            'loadNever' => LoadShedPolicy::neverList(),
            'loadCores' => LoadLevel::cpuCores(),
        ]);
    }

    public function saveSettings(Request $request, array $params = []): Response
    {
        $back = url('admin/system/surcharge');
        if (!Csrf::validate($request->inputString('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }
        $before = WatchSettings::load();
        $after = WatchSettings::fromForm($request->all(), $before, [WatchSettings::SHED_ENABLED]);
        // Ce formulaire ne touche pas aux seuils des détecteurs du mod.
        foreach (WatchSettings::keys() as $key) {
            if (!in_array($key, self::FORM_KEYS, true)) {
                $after[$key] = $before[$key];
            }
        }
        if (!self::persist(new PlatformSettingsRepository(), $after, $before, 'surcharge')) {
            Session::flash('error', 'Les réglages n’ont pas pu être enregistrés (table platform_settings absente ?).');

            return Response::redirect($back);
        }
        Session::flash('success', (int) $after[WatchSettings::SHED_ENABLED] === 1
            ? 'Réglages enregistrés. Le ralentissement sous charge est activé : il ne joue que sous saturation avérée.'
            : 'Réglages enregistrés. Le ralentissement sous charge est désactivé.');

        return Response::redirect($back);
    }

    /**
     * Enregistre les réglages modifiés, journalise le changement et applique tout de suite (état de charge recalculé,
     * index des formules reconstruit quand le ralentissement est activé). Partagé avec la page des alertes du mod.
     *
     * @param array<string, float|int> $after
     * @param array<string, float|int> $before
     */
    public static function persist(PlatformSettingsRepository $repo, array $after, array $before, string $origin): bool
    {
        $changed = [];
        try {
            if (!$repo->tableExists()) {
                return false;
            }
            $pairs = [];
            foreach ($after as $key => $value) {
                if (($before[$key] ?? null) != $value) {
                    $changed[$key] = ['avant' => $before[$key] ?? null, 'apres' => $value];
                }
                $pairs[$key] = (string) $value;
            }
            $repo->setMany($pairs);
        } catch (\Throwable) {
            return false;
        }
        if ($changed !== []) {
            AuditService::logPlatformAction(AuditAction::PLATFORM_LOAD_SETTINGS_UPDATED, ['page' => $origin, 'changes' => $changed]);
        }
        try {
            WatchFlusher::run(time(), null, false, null, true);
        } catch (\Throwable) {
        }

        return true;
    }

    private static function planLabel(string $slug): string
    {
        return ['free' => 'Gratuit', 'standard' => 'Standard', 'pro' => 'Pro', 'pro_plus' => 'Pro+'][$slug] ?? $slug;
    }
}
