<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array<string,mixed> $acquisition */
/** @var list<array<string,mixed>> $logs */
/** @var list<array<string,mixed>> $artifacts */
require __DIR__ . '/_subnav.php';
?>
<div class="breadcrumb">Athena / SSE / <a class="link" href="<?= $h(url('atak/sse/exploitation-numerique/acquisitions')) ?>">Acquisitions</a> / <strong><?= $h($acquisition['reference_code'] ?? '') ?></strong></div>
<div class="page-heading">
    <div>
        <div class="page-heading-overline"><?= $h($acquisition['method_label'] ?? '') ?> // <?= $h($acquisition['status_label'] ?? '') ?></div>
        <h1><?= $h($acquisition['reference_code'] ?? '') ?></h1>
        <p>Support <?= $h($acquisition['device_reference'] ?? '') ?> · <?= $h($acquisition['partial_label'] ?? '') ?></p>
    </div>
    <div class="page-reference">
        <strong><?= $h($acquisition['status_label'] ?? '') ?></strong>
        <div><a class="link" href="<?= $h(url('atak/sse/exploitation-numerique/supports/' . (int) ($acquisition['device_id'] ?? 0))) ?>">Fiche support</a></div>
    </div>
</div>
<div class="metrics-grid">
    <div class="metric"><div class="metric-label">Volume</div><div class="metric-value" style="font-size:1rem"><?= $h($acquisition['volume_label'] ?? '—') ?></div><div class="metric-detail">Exploitable</div></div>
    <div class="metric"><div class="metric-label">Fichiers</div><div class="metric-value"><?= (int) ($acquisition['file_count'] ?? 0) ?></div><div class="metric-detail">Indexés</div></div>
    <div class="metric"><div class="metric-label">Artefacts</div><div class="metric-value"><?= (int) ($acquisition['artifact_count'] ?? 0) ?></div><div class="metric-detail">Détectés</div></div>
    <div class="metric"><div class="metric-label">Intégrité</div><div class="metric-value" style="font-size:.85rem"><?= $h($acquisition['integrity_algo'] ?? 'SHA-256') ?></div><div class="metric-detail"><?= $h(mb_substr((string) ($acquisition['integrity_hash'] ?? '—'), 0, 16)) ?>…</div></div>
</div>
<?php if (!empty($acquisition['reserves'])): ?>
<section class="panel"><div class="panel-header"><div class="panel-title">Limites et réserves</div></div>
<div class="panel-body"><p><?= nl2br($h($acquisition['reserves'])) ?></p></div></section>
<?php endif; ?>
<section class="panel" style="margin-top:10px">
    <div class="panel-header"><div class="panel-title"><span class="panel-index">03.01</span> Journal technique</div></div>
    <div class="panel-body">
        <?php if ($logs === []): ?><p class="muted">Aucun journal.</p>
        <?php else: ?><ul class="iw-feed"><?php foreach ($logs as $log): ?>
            <li><time><?= $h(substr((string) ($log['logged_at'] ?? ''), 11, 8)) ?></time><span>[<?= $h($log['level'] ?? 'info') ?>] <?= $h($log['message'] ?? '') ?></span></li>
        <?php endforeach; ?></ul><?php endif; ?>
    </div>
</section>
<section class="panel" style="margin-top:10px">
    <div class="panel-header"><div class="panel-title"><span class="panel-index">03.02</span> Artefacts extraits</div>
        <a class="link" href="<?= $h(url('atak/sse/exploitation-numerique/artefacts?device_id=' . (int) ($acquisition['device_id'] ?? 0))) ?>">Visionneuse</a>
    </div>
    <?php if ($artifacts === []): ?>
        <div class="panel-body"><p class="muted">Aucun artefact.</p></div>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Nom</th><th>Catégorie</th><th>Intérêt</th><th>Statut</th><th></th></tr></thead>
            <tbody>
            <?php foreach (array_slice($artifacts, 0, 40) as $art): ?>
                <tr>
                    <td><?= $h($art['name'] ?? '') ?></td>
                    <td><?= $h($art['category_label'] ?? '') ?></td>
                    <td><?= $h($art['interest_level_label'] ?? '') ?></td>
                    <td><?= $h($art['status_label'] ?? '') ?></td>
                    <td><a class="btn-open" href="<?= $h(url('atak/sse/exploitation-numerique/artefacts/' . (int) $art['id'])) ?>">Fiche</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</section>
<?php $sseContent = ob_get_clean(); require dirname(__DIR__) . '/_layout.php';
