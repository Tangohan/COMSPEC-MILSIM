<?php
declare(strict_types=1);

$haloLoaderHint = $haloLoaderHint ?? 'Chargement…';
$baseUrl = $baseUrl ?? url('');
?>
<div id="halo-loader" class="halo-loader" role="status" aria-live="polite" aria-busy="true" aria-label="Chargement">
    <div class="halo-loader__stage" aria-hidden="true">
        <svg class="halo-loader__grid" data-halo-grid viewBox="0 0 320 320" width="320" height="320" focusable="false"></svg>
    </div>
    <p class="halo-loader__status" data-halo-status><?= htmlspecialchars($haloLoaderHint, ENT_QUOTES, 'UTF-8') ?></p>
    <span class="sr-only"><span data-halo-pct>0</span>%</span>
</div>
<script src="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/assets/js/halo-loader.js"></script>
