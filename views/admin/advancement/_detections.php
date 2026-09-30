<?php
$detections = is_array($detections ?? null) ? $detections : [];
if ($detections === []) {
    return;
}
$levelClass = [
    'danger' => 'adv-detect--danger',
    'warn' => 'adv-detect--warn',
    'info' => 'adv-detect--info',
];
?>
<section class="adv-panel adv-detect-panel" aria-label="Détections">
    <h2>Détections <?= adv_info('Détections', 'Contrôles automatiques : doublons, trous, quota, inéligibles inscrits, conflits d’intérêt, écarts d’ordre.') ?></h2>
    <ul class="adv-detect">
        <?php foreach ($detections as $hit): ?>
            <?php
            $lvl = (string) ($hit['level'] ?? 'info');
            $cls = $levelClass[$lvl] ?? 'adv-detect--info';
            ?>
            <li class="<?= $cls ?>"><?= adv_h((string) ($hit['message'] ?? '')) ?></li>
        <?php endforeach; ?>
    </ul>
</section>
