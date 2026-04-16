<?php
declare(strict_types=1);
/**
 * Ligne de liste cliquable (tableau de bord / centre d’actions).
 *
 * - $ui_row_title, $ui_row_href (obligatoires)
 * - $ui_row_subtitle (optionnel)
 * - $ui_row_meta (optionnel, texte court à droite)
 */
$ui_row_title = isset($ui_row_title) ? trim((string) $ui_row_title) : '';
$ui_row_href = isset($ui_row_href) ? trim((string) $ui_row_href) : '';
$ui_row_subtitle = isset($ui_row_subtitle) ? trim((string) $ui_row_subtitle) : '';
$ui_row_meta = isset($ui_row_meta) ? trim((string) $ui_row_meta) : '';
if ($ui_row_title === '' || $ui_row_href === '') {
    return;
}
?>
<li>
    <a href="<?= htmlspecialchars($ui_row_href, ENT_QUOTES, 'UTF-8') ?>" class="flex flex-wrap items-start justify-between gap-3 rounded-xl border border-slate-100 bg-white px-4 py-3 transition hover:border-slate-200 hover:bg-slate-50/80">
        <div class="min-w-0">
            <p class="text-sm font-semibold text-slate-900"><?= htmlspecialchars($ui_row_title, ENT_QUOTES, 'UTF-8') ?></p>
            <?php if ($ui_row_subtitle !== ''): ?>
            <p class="mt-0.5 text-xs text-slate-600"><?= htmlspecialchars($ui_row_subtitle, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
        </div>
        <?php if ($ui_row_meta !== ''): ?>
        <span class="shrink-0 text-xs font-medium text-slate-400"><?= htmlspecialchars($ui_row_meta, ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif; ?>
    </a>
</li>
