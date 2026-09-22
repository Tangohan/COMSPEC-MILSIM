<?php
declare(strict_types=1);

$haloLoaderHint = $haloLoaderHint ?? __('common.loading');
$haloLoaderSeenKey = $haloLoaderSeenKey ?? 'athena-halo-loader-tacmap';
$baseUrl = $baseUrl ?? url('');
$haloLoaderAssetVer = $haloLoaderAssetVer
    ?? (isset($assetVer) ? (string) $assetVer : null)
    ?? (function_exists('platform_app_version') ? (string) platform_app_version() : '1');
$haloJsStamp = (string) (@filemtime(dirname(__DIR__, 2) . '/public/assets/js/halo-loader.js') ?: time());
$haloCssStamp = (string) (@filemtime(dirname(__DIR__, 2) . '/public/assets/css/halo-loader.css') ?: time());
$haloLoaderVer = $haloLoaderAssetVer . '.' . $haloJsStamp . '.' . $haloCssStamp;
?>
<div id="halo-loader" class="halo-loader" role="status" aria-live="polite" aria-busy="true" aria-label="<?= htmlspecialchars(__('common.loading'), ENT_QUOTES, 'UTF-8') ?>" data-halo-seen-key="<?= htmlspecialchars($haloLoaderSeenKey, ENT_QUOTES, 'UTF-8') ?>">
    <div class="halo-loader__stage" aria-hidden="true">
        <svg class="halo-loader__grid" data-halo-grid viewBox="0 0 320 320" width="320" height="320" focusable="false"></svg>
    </div>
    <p class="halo-loader__status" data-halo-status><?= htmlspecialchars($haloLoaderHint, ENT_QUOTES, 'UTF-8') ?></p>
    <span class="sr-only"><span data-halo-pct>0</span>%</span>
</div>
<script src="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/assets/js/halo-loader.js?v=<?= htmlspecialchars($haloLoaderVer, ENT_QUOTES, 'UTF-8') ?>"></script>
