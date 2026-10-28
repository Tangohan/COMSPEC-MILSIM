<?php
declare(strict_types=1);

/**
 * Marques « équipe Athena » sur tout le site : aigle rouge pour l'administration de la plateforme, casque vert
 * pour le support. Feuille de style, correspondances (ids de comptes, slugs de fiche) et décorateur
 * public/assets/js/staff-marks.js. Aucune donnée personnelle n'est exposée. Inclus une fois par page, avant </body>.
 */
if (!empty($GLOBALS['__athStaffAssets'])) {
    return;
}
$GLOBALS['__athStaffAssets'] = true;
try {
    $athStaffMap = \App\Services\Platform\StaffMarkService::clientMap((int) (\App\Core\Session::get('tenant_id') ?? 0));
} catch (\Throwable) {
    $athStaffMap = ['u' => [], 's' => []];
}
if ($athStaffMap['u'] === []) {
    return;
}
$athStaffJson = json_encode($athStaffMap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}';
?>
<link href="<?= htmlspecialchars(asset_url('assets/css/staff-marks.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
<script type="application/json" id="ath-staff-map"><?= $athStaffJson ?></script>
<script src="<?= htmlspecialchars(asset_url('assets/js/staff-marks.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
