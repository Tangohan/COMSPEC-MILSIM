<?php
declare(strict_types=1);
/**
 * Aperçu live annonce communauté — motif DSFR (.ds-alert), aligné sur alert_banners.php.
 *
 * Variables attendues : $previewKindLabel, $previewTitle, $previewBody (opt), $previewTone (opt)
 */
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$previewKindLabel = $previewKindLabel ?? 'Information';
$previewTitle = $previewTitle ?? 'Titre de l\'annonce';
$previewBody = $previewBody ?? '';
$previewTone = $previewTone ?? 'info';

if (empty($GLOBALS['__dsfr_service_css'])) {
    $GLOBALS['__dsfr_service_css'] = true;
    echo '<link href="' . $h(asset_url('assets/css/dsfr-service.css')) . '" rel="stylesheet">';
}
?>
<div class="bo-ta-preview bo-ta-preview--dsfr" id="ta-live-preview" aria-live="polite">
    <p class="bo-ta-preview__caption">Aperçu bandeau classique (DSFR)</p>
    <div class="ds-alert ds-alert--<?= $h($previewTone) ?>" role="status">
        <p class="ds-alert__title" id="ta-preview-title"><?= $h($previewTitle) ?></p>
        <p class="bo-ta-preview__kind" id="ta-preview-kind"><?= $h($previewKindLabel) ?></p>
        <?php if ($previewBody !== ''): ?>
        <p id="ta-preview-body"><?= $h($previewBody) ?></p>
        <?php else: ?>
        <p id="ta-preview-body" class="hidden" hidden></p>
        <?php endif; ?>
    </div>
</div>
