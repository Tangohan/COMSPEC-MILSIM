<?php
declare(strict_types=1);
$tenant = is_array($platformTenant ?? null) ? $platformTenant : [];
$plans = is_array($platformSubscriptionPlans ?? null) ? $platformSubscriptionPlans : [];
$plansError = isset($platformSubscriptionPlansError) ? (string) $platformSubscriptionPlansError : '';
$statusLabels = is_array($platformSubscriptionStatusLabels ?? null) ? $platformSubscriptionStatusLabels : [];
$typeOptions = is_array($platformTenantTypes ?? null) ? $platformTenantTypes : [];
$currentType = (string) ($platformTenantCurrentType ?? 'full');
$identityAction = (string) ($platformTenantIdentityFormAction ?? '');
$typeAction = (string) ($platformTenantTypeFormAction ?? '');
$formAction = (string) ($platformTenantPlanFormAction ?? '');
$founderTrialEndsAt = isset($platformFounderTrialEndsAt) ? (string) $platformFounderTrialEndsAt : '';
$name = (string) ($tenant['name'] ?? '');
$slug = (string) ($tenant['slug'] ?? '');
$communityCode = trim((string) ($tenant['community_code'] ?? ''));
$createdAt = isset($tenant['created_at']) && $tenant['created_at'] !== null && $tenant['created_at'] !== ''
    ? (string) $tenant['created_at']
    : '';
$createdLabel = '—';
if ($createdAt !== '') {
    $createdTs = strtotime($createdAt);
    $createdLabel = $createdTs ? date('d/m/Y', $createdTs) : $createdAt;
}
$tenantId = (int) ($tenant['id'] ?? 0);
$currentPlan = (string) ($tenant['plan_slug'] ?? 'free');
$currentStatus = (string) ($tenant['subscription_status'] ?? 'none');
$periodEnd = isset($tenant['subscription_current_period_end']) && $tenant['subscription_current_period_end'] !== null && $tenant['subscription_current_period_end'] !== ''
    ? (string) $tenant['subscription_current_period_end']
    : '';
$stripeSub = trim((string) ($tenant['stripe_subscription_id'] ?? ''));
$hasStripe = $stripeSub !== '';
$trialActive = false;
if ($founderTrialEndsAt !== '') {
    $trialTs = strtotime($founderTrialEndsAt);
    $trialActive = $trialTs !== false && $trialTs > time();
}
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$publicUrl = $slug !== '' ? url('c/' . rawurlencode($slug)) : '';
$usersUrl = $tenantId > 0 ? url('admin/users') . '?tenant_id=' . $tenantId : url('admin/users');
// La pastille montre la formule réellement appliquée (formule offerte, essai fondateur) ; le formulaire garde la formule de base.
$effectivePlan = \App\Support\PlanGift::effectivePlanSlug($tenant);
$planGift = \App\Support\PlanGift::activeFromSettings($tenant['settings'] ?? null);
$planNames = [];
foreach ($plans as $p) {
    $planNames[(string) ($p['slug'] ?? '')] = (string) ($p['name'] ?? '');
}
$currentPlanLabel = ($planNames[$effectivePlan] ?? '') !== '' ? $planNames[$effectivePlan] : $effectivePlan;
$basePlanLabel = ($planNames[$currentPlan] ?? '') !== '' ? $planNames[$currentPlan] : $currentPlan;
$giftEndLabel = '';
if ($planGift !== null && !empty($planGift['ends_at'])) {
    $giftEndTs = strtotime((string) $planGift['ends_at']);
    $giftEndLabel = $giftEndTs ? date('d/m/Y', $giftEndTs) : (string) $planGift['ends_at'];
}
$currentTypeLabel = (string) ($typeOptions[$currentType]['label'] ?? $currentType);
$ok = \App\Core\Session::getFlash('success');
$err = \App\Core\Session::getFlash('error');
$tab = (string) ($platformTab ?? 'resume');
$tabLabels = ['resume' => 'Résumé', 'edition' => 'Édition', 'membres' => 'Membres', 'diagnostic' => 'Diagnostic'];
if (!isset($tabLabels[$tab])) {
    $tab = 'resume';
}
$editUrl = (string) ($platformEditUrl ?? url('admin/tenants/' . $tenantId . '/edit'));
$archived = is_array($platformTenantArchive ?? null);
$fmtDate = static function (mixed $raw): string {
    $t = ($raw !== null && $raw !== '' && $raw !== false) ? strtotime((string) $raw) : false;

    return $t ? date('d/m/Y H:i', $t) : '—';
};
?>
<div class="pa">
    <div class="pa__frame">
        <header class="pa-hero">
            <p class="pa-crumb">
                <a href="<?= $h(url('admin')) ?>">Administration du site</a>
                <span aria-hidden="true"> / </span>
                <a href="<?= $h(url('admin/tenants')) ?>">Communautés</a>
                <span aria-hidden="true"> / </span>
                Fiche communauté
            </p>
            <h1 class="pa-hero__title"><?= $h($name !== '' ? $name : 'Communauté') ?></h1>
            <p class="pa-hero__lead">
                Résumé, édition par section, membres et diagnostic de la communauté. Les menus, unités et le recrutement se gèrent dans le back-office de cette communauté.
            </p>
            <div class="pa-chips">
                <span class="pa-pill pa-pill--slate"><?= $h($currentTypeLabel) ?></span>
                <span class="pa-pill pa-pill--mint"><?= $h($currentPlanLabel) ?></span>
                <?php if ($planGift !== null): ?>
                    <span class="pa-pill pa-pill--mint">Offerte <?= $giftEndLabel !== '' ? 'jusqu’au ' . $h($giftEndLabel) : 'sans limite' ?></span>
                <?php endif; ?>
                <span class="pa-pill pa-pill--amber"><?= $h((string) ($statusLabels[$currentStatus] ?? 'Inconnu')) ?></span>
                <?php if ($archived): ?>
                    <span class="pa-pill pa-pill--rose">Archivée</span>
                <?php endif; ?>
                <?php if ($trialActive): ?>
                    <span class="pa-pill pa-pill--mint">Essai fondateur en cours</span>
                <?php endif; ?>
            </div>
            <div class="pa-hero__actions">
                <?php if ($publicUrl !== ''): ?>
                    <a class="pa-btn pa-btn--solid" href="<?= $h($publicUrl) ?>" target="_blank" rel="noopener">Page publique</a>
                <?php endif; ?>
                <a class="pa-btn pa-btn--ghost" href="<?= $h($usersUrl) ?>">Comptes</a>
                <a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/formules-offertes') . '?tenant_id=' . $tenantId) ?>">Offrir une formule</a>
                <a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/audit')) ?>">Journal d’audit</a>
            </div>
        </header>

        <div class="pa-panel">
            <div class="pa-narrow">
                <?php if ($ok): ?>
                    <p class="pa-flash pa-flash--ok"><?= $h((string) $ok) ?></p>
                <?php endif; ?>
                <?php if ($err): ?>
                    <p class="pa-flash pa-flash--err"><?= $h((string) $err) ?></p>
                <?php endif; ?>

                <nav class="pa-tabs" aria-label="Sections de la fiche communauté">
                    <?php foreach ($tabLabels as $tabKey => $tabLabel): ?>
                        <a class="pa-tab<?= $tab === $tabKey ? ' is-active' : '' ?>" href="<?= $h($editUrl . ($tabKey === 'resume' ? '' : '?onglet=' . $tabKey)) ?>"<?= $tab === $tabKey ? ' aria-current="page"' : '' ?>><?= $h($tabLabel) ?></a>
                    <?php endforeach; ?>
                </nav>

                <?php require __DIR__ . '/tenant_edit/' . $tab . '.php'; ?>
            </div>
        </div>
    </div>
</div>
