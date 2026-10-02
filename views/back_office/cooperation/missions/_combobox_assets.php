<?php
declare(strict_types=1);

/** Ressources du combobox de recherche (une seule inclusion par page). */
if (!empty($GLOBALS['__coopComboboxAssets'])) {
    return;
}
$GLOBALS['__coopComboboxAssets'] = true;
?>
<template id="coop-skeleton-template"><?php $variant = 'rows'; $rows = 3; require base_path('views/partials/ui/skeleton.php'); ?></template>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/cooperation/combobox.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
