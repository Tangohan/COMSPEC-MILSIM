<?php
/**
 * Amorçage du guide visuel du tableau de bord.
 *
 * @var array{enabled?: bool, key?: string, dismissed?: bool, csrf?: string, save_url?: string}|null $dashboard_ui_tour
 */
$tour = is_array($dashboard_ui_tour ?? null) ? $dashboard_ui_tour : [];
if (empty($tour['enabled'])) {
    return;
}
$flags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
$payload = json_encode([
    'enabled' => true,
    'key' => (string) ($tour['key'] ?? 'dashboard.v1'),
    'dismissed' => !empty($tour['dismissed']),
    'csrf' => (string) ($tour['csrf'] ?? ''),
    'save_url' => (string) ($tour['save_url'] ?? ''),
], $flags);
if (!is_string($payload) || $payload === '') {
    $payload = '{}';
}
?>
<link href="<?= htmlspecialchars(asset_url('assets/css/dashboard-ui-tour.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
<script>window.__dashboardUiTour = <?= $payload ?>;</script>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/dashboard-ui-tour.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
