<?php
declare(strict_types=1);
/** @var array<string, mixed>|null $analyticsBeacon */
$analyticsBeacon = $analyticsBeacon ?? null;
if (!is_array($analyticsBeacon) || empty($analyticsBeacon['tenantId']) || empty($analyticsBeacon['durationEvent']) || empty($analyticsBeacon['category'])) {
    return;
}
$beaconPayload = [
    'tenantId' => (int) $analyticsBeacon['tenantId'],
    'category' => (string) $analyticsBeacon['category'],
    'durationEvent' => (string) $analyticsBeacon['durationEvent'],
    'subjectType' => isset($analyticsBeacon['subjectType']) ? (string) $analyticsBeacon['subjectType'] : '',
    'subjectId' => isset($analyticsBeacon['subjectId']) ? (int) $analyticsBeacon['subjectId'] : 0,
    'beaconUrl' => url('analytics/beacon'),
    'csrf' => \App\Core\Csrf::token(),
];
?>
<script>
window.__COMSPEC_ANALYTICS__ = <?= json_encode($beaconPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script defer src="<?= htmlspecialchars(url('assets/js/comspec-analytics.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
