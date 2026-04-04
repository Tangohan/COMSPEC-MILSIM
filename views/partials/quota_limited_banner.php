<?php
/**
 * Bandeau quota (gratuit limité).
 *
 * @var array<string, mixed>|null $quotaBanner Résultat de quotaStatusForFeature
 * @var bool $quotaCanProceed Ex. canCreateEvent — false si quota épuisé
 * @var string $variant light (admin clair) | dark (pages communauté sombres)
 */
$quotaBanner = $quotaBanner ?? null;
$quotaCanProceed = $quotaCanProceed ?? true;
$variant = $variant ?? 'light';
$quotaFromKey = $quotaFromKey ?? 'events';

if (!is_array($quotaBanner) || ($quotaBanner['mode'] ?? '') !== 'limited') {
    return;
}
$used = (int) ($quotaBanner['used'] ?? 0);
$limit = (int) ($quotaBanner['limit'] ?? 0);
$remaining = (int) ($quotaBanner['remaining'] ?? 0);
$threshold = (float) ($quotaBanner['soft_block_threshold'] ?? 0.8);
$ratio = $limit > 0 ? $used / $limit : 0.0;
$showSoft = $ratio >= $threshold && $limit > 0;
$msg = trim((string) ($quotaBanner['soft_block_message'] ?? ''));
$upgradePath = (string) ($quotaBanner['upgrade_cta'] ?? 'platform/upgrade');
$baseUpgrade = url($upgradePath);
$sep = str_contains($baseUpgrade, '?') ? '&' : '?';
$upgradeUrl = $baseUpgrade . $sep . 'from=' . rawurlencode('quota_' . $quotaFromKey);

if ($variant === 'dark') {
    ?>
    <div class="text-xs text-neutral-400 mb-4 border border-white/10 rounded-lg px-3 py-2 bg-neutral-900/40 space-y-1">
        <p>
            Créations d’événements (organisation) : <?= $used ?> / <?= $limit ?> ce mois
            (<?= $remaining ?> restante<?= $remaining !== 1 ? 's' : '' ?>).
        </p>
        <?php if ($showSoft && $msg !== ''): ?>
            <p class="text-amber-400/90"><?= htmlspecialchars($msg) ?></p>
        <?php endif; ?>
        <?php if (!$quotaCanProceed): ?>
            <p>
                <a href="<?= htmlspecialchars($upgradeUrl) ?>" class="text-emerald-400 font-semibold underline">Débloquer sans limite</a>
            </p>
        <?php endif; ?>
    </div>
    <?php
    return;
}
?>
<div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 space-y-2">
    <p>
        <span class="font-semibold">Créations ce mois :</span>
        <?= $used ?> / <?= $limit ?>
        (<?= $remaining ?> restante<?= $remaining !== 1 ? 's' : '' ?>).
        <?php if (!$quotaCanProceed): ?>
            <a href="<?= htmlspecialchars($upgradeUrl) ?>" class="ml-2 font-semibold text-amber-800 underline">Passer à un plan supérieur</a>
        <?php endif; ?>
    </p>
    <?php if ($showSoft && $msg !== ''): ?>
        <p class="text-amber-800/90 text-xs"><?= htmlspecialchars($msg) ?></p>
    <?php endif; ?>
    <?php if (!$quotaCanProceed): ?>
        <p class="text-xs text-amber-900/80">Vous pouvez toujours consulter les événements et les membres peuvent répondre aux invitations (RSVP).</p>
    <?php endif; ?>
</div>
