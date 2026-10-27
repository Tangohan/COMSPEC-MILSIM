<?php
declare(strict_types=1);

/**
 * Marques « donateur » sur tout le site : feuille de style, correspondances (ids, slugs, communautés soutien)
 * et décorateur public/assets/js/donor-marks.js. Aucune donnée personnelle n'est exposée (ni e-mail ni montant).
 * Inclus une fois par mise en page, juste avant </body>.
 */
if (!empty($GLOBALS['__athDonorAssets'])) {
    return;
}
$GLOBALS['__athDonorAssets'] = true;
try {
    $athDonorMap = \App\Services\Billing\DonorService::clientMap((int) (\App\Core\Session::get('tenant_id') ?? 0));
} catch (\Throwable) {
    $athDonorMap = ['u' => [], 's' => [], 't' => [], 'ts' => []];
}
if ($athDonorMap['u'] === [] && $athDonorMap['t'] === []) {
    return;
}
$athDonorJson = json_encode($athDonorMap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}';
?>
<link href="<?= htmlspecialchars(asset_url('assets/css/donor-marks.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
<script type="application/json" id="ath-donor-map"><?= $athDonorJson ?></script>
<script src="<?= htmlspecialchars(asset_url('assets/js/donor-marks.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
