<?php
declare(strict_types=1);
/**
 * Messages flash back-office — rendu DSFR (.ds-alert).
 *
 * Lit les flashes session success / error / warning / info, ou variables explicites :
 *   $success, $error, $warning, $info
 *
 * Ne pas utiliser pour les bandeaux « Attention » (breaking) ni les encarts à kicker
 * (ath-banner-warn__kicker) — ceux-là restent en place.
 *
 * Optionnel : $bo_flash_margin_class (défaut « ath-rise »)
 */
use App\Core\Session;

$boFlashMargin = isset($bo_flash_margin_class) ? (string) $bo_flash_margin_class : 'ath-rise';

$boFlashMap = [
    'success' => 'success',
    'error' => 'error',
    'warning' => 'warning',
    'info' => 'info',
];

foreach ($boFlashMap as $key => $variant) {
    $raw = null;
    if (isset(${$key}) && ${$key} !== null && ${$key} !== '') {
        $raw = (string) ${$key};
    } else {
        $fromSession = Session::getFlash($key);
        if ($fromSession !== null && $fromSession !== '') {
            $raw = (string) $fromSession;
        }
    }
    if ($raw === null || trim($raw) === '') {
        continue;
    }
    $flash_message = $raw;
    $flash_variant = $variant;
    $flash_margin_class = $boFlashMargin;
    include __DIR__ . '/flash_message.php';
}
