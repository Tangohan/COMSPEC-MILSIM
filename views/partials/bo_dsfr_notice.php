<?php
declare(strict_types=1);
/**
 * Encart DSFR persistant (hors flash session) — info / avertissement.
 * Variables : $notice_tone ('info'|'warning'|'error'|'success'), $notice_title, $notice_body (HTML autorisé côté vue)
 */
$noticeTone = $notice_tone ?? 'info';
$noticeTitle = $notice_title ?? '';
$noticeBody = $notice_body ?? '';
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

if (empty($GLOBALS['__dsfr_service_css'])) {
    $GLOBALS['__dsfr_service_css'] = true;
    echo '<link href="' . $h(asset_url('assets/css/dsfr-service.css')) . '" rel="stylesheet">';
}
?>
<div class="ds-alert ds-alert--<?= $h($noticeTone) ?> ath-rise" role="<?= $noticeTone === 'error' ? 'alert' : 'status' ?>">
    <?php if ($noticeTitle !== ''): ?>
    <p class="ds-alert__title"><?= $h($noticeTitle) ?></p>
    <?php endif; ?>
    <?php if ($noticeBody !== ''): ?>
    <div class="ds-alert__body"><?= $noticeBody ?></div>
    <?php endif; ?>
</div>
