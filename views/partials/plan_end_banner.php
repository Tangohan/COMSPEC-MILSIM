<?php
declare(strict_types=1);

// Bandeau « votre formule se termine » pour les gestionnaires de la communauté (propriétaire, fondateurs,
// administrateurs) : N jours avant la fin d'un essai fondateur, d'une formule offerte ou d'une période payée
// par PayPal, puis pendant la période de grâce si elle est activée, et « formule expirée » après une période payée. Textes FR/EN écrits dans App\Support\PlanAccessWindow (translate="no").

use App\Core\Session;
use App\Support\PlanAccessWindow;
use App\Support\PlanGift;

$planEndUserId = (int) (Session::get('user_id') ?? 0);
$planEndTenantId = (int) (Session::get('tenant_id') ?? 0);
if ($planEndUserId < 1 || $planEndTenantId < 2) {
    return;
}

// Calcul mis en cache 10 minutes par session et par communauté.
$planEndCacheKey = 'plan_end_banner_' . $planEndTenantId;
$planEndCache = Session::get($planEndCacheKey);
if (!is_array($planEndCache) || (int) ($planEndCache['at'] ?? 0) < time() - 600) {
    $planEndCache = ['at' => time(), 'banner' => null];
    try {
        $planEndTenant = \App\Core\Container::get(\App\Repositories\TenantRepository::class)->findById($planEndTenantId);
        if (is_array($planEndTenant)) {
            $planEndUsers = \App\Core\Container::get(\App\Repositories\UserRepository::class);
            $planEndMe = $planEndUsers->findById($planEndUserId);
            $planEndEmail = strtolower(trim((string) ($planEndMe['email'] ?? '')));
            $planEndIsManager = (int) ($planEndTenant['owner_user_id'] ?? 0) === $planEndUserId
                || ($planEndEmail !== '' && in_array($planEndEmail, array_map(
                    static fn ($e): string => strtolower(trim((string) $e)),
                    array_merge(
                        $planEndUsers->listGovernanceEmailsForTenant($planEndTenantId),
                        $planEndUsers->listAdministratorEmailsForTenant($planEndTenantId),
                    )
                ), true));
            if ($planEndIsManager) {
                $planEndSvc = new \App\Services\Platform\PlanEndWarningService();
                $planEndCfg = $planEndSvc->config();
                $planEndGraceDays = $planEndCfg['grace_enabled'] ? $planEndCfg['grace_days'] : 0;
                $planEndPlans = \App\Core\Container::get(\App\Repositories\SubscriptionPlanRepository::class);
                $planEndName = static function (string $slug) use ($planEndPlans): string {
                    $p = $planEndPlans->findBySlug($slug);

                    return trim((string) ($p['name'] ?? '')) ?: $slug;
                };
                $planEndHasAtak = static function (string $slug) use ($planEndPlans): bool {
                    $p = $planEndPlans->findBySlug($slug);
                    $f = $p ? json_decode((string) ($p['features_json'] ?? '{}'), true) : [];

                    return is_array($f) && !empty($f['atak']);
                };
                $planEndPaidDays = PlanAccessWindow::paidReminderDays($planEndCfg['warning_days']);
                $planEndSoon = PlanAccessWindow::endingSoon($planEndTenant, max($planEndPaidDays, $planEndCfg['warning_days']));
                if ($planEndSoon !== null
                    && $planEndSoon['days_left'] > ($planEndSoon['kind'] === 'paid' ? $planEndPaidDays : $planEndCfg['warning_days'])) {
                    $planEndSoon = null;
                }
                if ($planEndSoon !== null) {
                    $planEndCache['banner'] = [
                        'mode' => 'ending',
                        'tenant_name' => (string) ($planEndTenant['name'] ?? ''),
                        'plan_name' => $planEndName($planEndSoon['plan_slug']),
                        'kind' => $planEndSoon['kind'],
                        'ends_at' => $planEndSoon['ends_at'],
                        'atak_lost' => $planEndHasAtak($planEndSoon['plan_slug'])
                            && !$planEndHasAtak($planEndSoon['kind'] === 'paid' ? 'free' : (string) ($planEndTenant['plan_slug'] ?? 'free')),
                        'grace_days' => $planEndGraceDays,
                    ];
                } else {
                    $planEndGrace = PlanAccessWindow::grace($planEndTenant, $planEndGraceDays);
                    $planEndExpired = PlanAccessWindow::paidExpired($planEndTenant);
                    if ($planEndGrace !== null) {
                        $planEndCache['banner'] = [
                            'mode' => 'grace',
                            'plan_name' => $planEndName($planEndGrace['plan_slug']),
                            'grace_ends_at' => $planEndGrace['grace_ends_at'],
                        ];
                    } elseif ($planEndExpired !== null) {
                        $planEndCache['banner'] = [
                            'mode' => 'expired',
                            'plan_name' => $planEndName($planEndExpired['plan_slug']),
                            'ended_at' => $planEndExpired['ended_at'],
                        ];
                    }
                }
            }
        }
    } catch (\Throwable) {
        // Base pas à jour ou dépôt indisponible : pas de bandeau.
    }
    Session::set($planEndCacheKey, $planEndCache);
}

$planEndBanner = is_array($planEndCache['banner'] ?? null) ? $planEndCache['banner'] : null;
if ($planEndBanner === null) {
    return;
}

$planEndLang = PlanGift::locale(function_exists('locale') ? (string) locale() : 'fr');
if ($planEndBanner['mode'] === 'grace') {
    $planEndCopy = PlanAccessWindow::graceCopy($planEndLang, (string) $planEndBanner['plan_name'], (string) $planEndBanner['grace_ends_at']);
    $planEndText = $planEndCopy['text'];
    $planEndCta = $planEndCopy['cta'];
} elseif ($planEndBanner['mode'] === 'expired') {
    $planEndCopy = PlanAccessWindow::paidExpiredCopy($planEndLang, (string) $planEndBanner['plan_name'], (string) $planEndBanner['ended_at']);
    $planEndText = $planEndCopy['text'];
    $planEndCta = $planEndCopy['cta'];
} else {
    $planEndCopy = PlanAccessWindow::copy(
        $planEndLang,
        (string) $planEndBanner['tenant_name'],
        (string) $planEndBanner['plan_name'],
        (string) $planEndBanner['kind'],
        (string) $planEndBanner['ends_at'],
        !empty($planEndBanner['atak_lost']),
        (int) $planEndBanner['grace_days']
    );
    $planEndText = $planEndCopy['banner'] . ' ' . $planEndCopy['lost'] . ($planEndCopy['grace'] !== '' ? ' ' . $planEndCopy['grace'] : '');
    $planEndCta = $planEndCopy['banner_cta'];
}
$planEndDismissKey = 'athPlanEnd:' . $planEndTenantId . ':' . md5($planEndText);
$he = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<div class="plan-end-banner" id="plan-end-banner" role="status" translate="no" lang="<?= $he($planEndLang) ?>" data-dismiss-key="<?= $he($planEndDismissKey) ?>">
    <p class="plan-end-banner__text"><?= $he($planEndText) ?></p>
    <div class="plan-end-banner__actions">
        <a class="plan-end-banner__cta" href="<?= $he(url('platform/upgrade')) ?>"><?= $he($planEndCta) ?></a>
        <button type="button" class="plan-end-banner__close" aria-label="<?= $planEndLang === 'en' ? 'Hide for today' : 'Masquer pour aujourd’hui' ?>">×</button>
    </div>
</div>
<style>
.plan-end-banner{position:relative;z-index:99;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:8px 16px;padding:10px 16px;background:#fef3c7;color:#451a03;border-bottom:1px solid #f59e0b;font-size:14px;line-height:1.45}
.plan-end-banner__text{margin:0;flex:1 1 320px;max-width:72rem}
.plan-end-banner__actions{display:flex;align-items:center;gap:8px}
.plan-end-banner__cta{display:inline-flex;align-items:center;min-height:36px;padding:6px 14px;border-radius:8px;background:#92400e;color:#fff;font-weight:700;text-decoration:none;white-space:nowrap}
.plan-end-banner__close{min-width:36px;min-height:36px;border:0;background:transparent;color:inherit;font-size:20px;line-height:1;cursor:pointer;border-radius:8px}
.plan-end-banner__close:hover{background:rgba(146,64,14,.12)}
html[data-bo-theme="dark"] .plan-end-banner{background:#422006;color:#fef3c7;border-color:#b45309}
html[data-bo-theme="dark"] .plan-end-banner__cta{background:#f59e0b;color:#1c1917}
</style>
<script>
(function () {
    var el = document.getElementById('plan-end-banner');
    if (!el) return;
    var key = el.getAttribute('data-dismiss-key') + ':' + new Date().toISOString().slice(0, 10);
    try { if (localStorage.getItem(key) === '1') { el.remove(); return; } } catch (e) {}
    var btn = el.querySelector('.plan-end-banner__close');
    if (btn) btn.addEventListener('click', function () {
        try { localStorage.setItem(key, '1'); } catch (e) {}
        el.remove();
    });
})();
</script>
