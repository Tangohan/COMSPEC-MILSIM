<?php
declare(strict_types=1);

// Sélecteur « Aperçu de la formule » de la barre du back-office : administrateur de la plateforme seulement.
$ppTenantId = (int) (\App\Core\Session::get('tenant_id') ?? 0);
if ($ppTenantId < 1 || !\App\Services\Platform\EffectivePlan::mayPreview()) {
    return;
}
$ppPlans = [];
try {
    $ppPlans = \App\Core\Container::get(\App\Repositories\SubscriptionPlanRepository::class)->allOrdered();
} catch (\Throwable) {
}
$ppCurrent = \App\Services\Platform\EffectivePlan::current();
$ppViews = [];
$ppRealType = \App\Services\Community\TenantTypeConfig::TYPE_FULL;
try {
    $ppTenant = \App\Core\Container::get(\App\Repositories\TenantRepository::class)->findById($ppTenantId);
    $ppRealType = \App\Services\Community\TenantTypeConfig::normalizeType((string) ($ppTenant['tenant_type'] ?? 'full'));
    $ppViews = \App\Services\Platform\EffectiveTenantType::previewable($ppRealType);
} catch (\Throwable) {
}
$ppView = \App\Services\Platform\EffectiveTenantType::current();
?>
<?php if (count($ppViews) > 1): ?>
<form method="post" action="<?= htmlspecialchars(url('admin/apercu-formule/vue'), ENT_QUOTES, 'UTF-8') ?>" class="ath-topbar__plan-preview" style="display:flex;align-items:center;gap:6px;">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
    <label for="view-preview-select" style="font-size:12px;">Aperçu de la vue</label>
    <select id="view-preview-select" name="view_type" onchange="this.form.submit()" style="font-size:12px;max-width:150px;">
        <option value="">Vue réelle</option>
        <?php foreach ($ppViews as $ppType): ?>
            <?php if ($ppType === $ppRealType) { continue; } ?>
            <option value="<?= htmlspecialchars($ppType, ENT_QUOTES, 'UTF-8') ?>"<?= $ppView !== null && $ppView['type'] === $ppType ? ' selected' : '' ?>><?= htmlspecialchars(\App\Services\Community\TenantTypeConfig::label($ppType), ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
    </select>
    <noscript><button type="submit" style="font-size:12px;">Voir</button></noscript>
</form>
<?php endif; ?>
<?php if ($ppPlans !== []): ?>
<form method="post" action="<?= htmlspecialchars(url('admin/apercu-formule'), ENT_QUOTES, 'UTF-8') ?>" class="ath-topbar__plan-preview" style="display:flex;align-items:center;gap:6px;">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
    <label for="plan-preview-select" style="font-size:12px;">Aperçu de la formule</label>
    <select id="plan-preview-select" name="plan_slug" onchange="this.form.submit()" style="font-size:12px;max-width:150px;">
        <option value="">Formule réelle</option>
        <?php foreach ($ppPlans as $ppPlan): ?>
            <?php $ppSlug = (string) ($ppPlan['slug'] ?? ''); ?>
            <option value="<?= htmlspecialchars($ppSlug, ENT_QUOTES, 'UTF-8') ?>"<?= $ppCurrent !== null && $ppCurrent['slug'] === $ppSlug ? ' selected' : '' ?>><?= htmlspecialchars(trim((string) ($ppPlan['name'] ?? '')) ?: $ppSlug, ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
    </select>
    <noscript><button type="submit" style="font-size:12px;">Voir</button></noscript>
</form>
<?php endif; ?>
