<?php
declare(strict_types=1);
/**
 * État vide standardisé.
 *
 * - $ui_empty_title (string)
 * - $ui_empty_description (string, optionnel)
 * - $ui_empty_primary_label + $ui_empty_primary_href (optionnel)
 * - $ui_empty_secondary_label + $ui_empty_secondary_href (optionnel)
 */
$ui_empty_title = isset($ui_empty_title) ? trim((string) $ui_empty_title) : '';
$ui_empty_description = isset($ui_empty_description) ? trim((string) $ui_empty_description) : '';
$ui_empty_primary_label = isset($ui_empty_primary_label) ? trim((string) $ui_empty_primary_label) : '';
$ui_empty_primary_href = isset($ui_empty_primary_href) ? trim((string) $ui_empty_primary_href) : '';
$ui_empty_secondary_label = isset($ui_empty_secondary_label) ? trim((string) $ui_empty_secondary_label) : '';
$ui_empty_secondary_href = isset($ui_empty_secondary_href) ? trim((string) $ui_empty_secondary_href) : '';
if ($ui_empty_title === '') {
    return;
}
?>
<div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/80 px-6 py-14 text-center md:px-10">
    <p class="text-base font-bold text-slate-800"><?= htmlspecialchars($ui_empty_title, ENT_QUOTES, 'UTF-8') ?></p>
    <?php if ($ui_empty_description !== ''): ?>
    <p class="mt-2 mx-auto max-w-md text-sm text-slate-600"><?= htmlspecialchars($ui_empty_description, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <?php if (($ui_empty_primary_label !== '' && $ui_empty_primary_href !== '') || ($ui_empty_secondary_label !== '' && $ui_empty_secondary_href !== '')): ?>
    <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
        <?php if ($ui_empty_primary_label !== '' && $ui_empty_primary_href !== ''): ?>
        <?php $ui_btn_variant = 'primary'; $ui_btn_label = $ui_empty_primary_label; $ui_btn_href = $ui_empty_primary_href; require base_path('views/partials/ui/button.php'); ?>
        <?php endif; ?>
        <?php if ($ui_empty_secondary_label !== '' && $ui_empty_secondary_href !== ''): ?>
        <?php $ui_btn_variant = 'secondary'; $ui_btn_label = $ui_empty_secondary_label; $ui_btn_href = $ui_empty_secondary_href; require base_path('views/partials/ui/button.php'); ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
