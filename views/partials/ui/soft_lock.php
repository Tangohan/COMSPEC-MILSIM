<?php
/**
 * Soft-lock Premium : aperçu flouté + CTA upgrade.
 * @var string $title
 * @var string $text
 * @var string $cta_href
 * @var string $cta_label
 * @var string|null $preview_html Contenu HTML déjà échappé / partiel sûr
 */
$title = (string) ($title ?? 'Fonctionnalité premium');
$text = (string) ($text ?? 'Disponible avec une offre supérieure pour votre communauté.');
$cta_href = (string) ($cta_href ?? url('platform/upgrade'));
$cta_label = (string) ($cta_label ?? 'Voir les offres');
$preview_html = $preview_html ?? null;
?>
<div class="ds-soft-lock">
    <div class="ds-soft-lock__blur p-6" aria-hidden="true">
        <?php if (is_string($preview_html) && $preview_html !== ''): ?>
            <?= $preview_html ?>
        <?php else: ?>
            <div class="space-y-3">
                <div class="ds-skeleton ds-skeleton--title"></div>
                <div class="ds-skeleton ds-skeleton--text" style="width:90%"></div>
                <div class="ds-skeleton ds-skeleton--text" style="width:70%"></div>
                <div class="h-32 rounded-xl bg-slate-200"></div>
            </div>
        <?php endif; ?>
    </div>
    <div class="ds-soft-lock__cta">
        <span class="ds-tag ds-tag--locked">Offre supérieure</span>
        <p class="text-base font-bold text-slate-900"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></p>
        <p class="max-w-sm text-sm text-slate-600"><?= htmlspecialchars($text, ENT_QUOTES, 'UTF-8') ?></p>
        <a href="<?= htmlspecialchars($cta_href, ENT_QUOTES, 'UTF-8') ?>" class="ds-btn ds-btn--primary"><?= htmlspecialchars($cta_label, ENT_QUOTES, 'UTF-8') ?></a>
    </div>
</div>
