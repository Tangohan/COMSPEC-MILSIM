<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
require __DIR__ . '/_subnav.php';
?>
<div class="breadcrumb">Athena / SSE / <a class="link" href="<?= $h(url('atak/sse/exploitation-numerique')) ?>">Exploitation numérique</a> / <strong>Rapports</strong></div>
<div class="page-heading">
    <div>
        <div class="page-heading-overline">Laboratoire // Comptes rendus</div>
        <h1>Rapports d’exploitation</h1>
        <p>Synthèse structurée : faits d’extraction, réserves, signaux et analyse opérateur.</p>
    </div>
</div>
<form class="toolbar" method="get">
    <div class="toolbar-field"><label for="device_id">Support</label>
        <select id="device_id" name="device_id"><option value="">Choisir…</option>
            <?php foreach ($devices as $d): ?>
                <option value="<?= (int) $d['id'] ?>" <?= !empty($device) && (int) $device['id'] === (int) $d['id'] ? 'selected' : '' ?>><?= $h($d['reference_code'] ?? '') ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="toolbar-actions"><button class="btn" type="submit">Générer la synthèse</button></div>
</form>
<?php if (empty($device)): ?>
<section class="panel"><div class="panel-body"><p class="muted">Sélectionnez un support pour produire le rapport d’exploitation.</p></div></section>
<?php else: ?>
<section class="panel">
    <div class="panel-header"><div class="panel-title">Rapport d’exploitation — <?= $h($device['reference_code'] ?? '') ?></div></div>
    <div class="panel-body" style="display:grid;gap:14px">
        <div><strong>1. Objet de l’exploitation</strong><p>Exploitation numérique du support <?= $h($device['reference_code'] ?? '') ?> (<?= $h($device['device_type_label'] ?? '') ?>).</p></div>
        <div><strong>2. Support examiné</strong><p><?= $h(trim(($device['manufacturer'] ?? '') . ' ' . ($device['model'] ?? '')) ?: '—') ?> · série <?= $h($device['serial_number'] ?? '—') ?></p></div>
        <div><strong>3. État du support</strong><p><?= $h($device['power_state_label'] ?? '—') ?> · <?= $h($device['locked_label'] ?? '') ?> · statut <?= $h($device['status_label'] ?? '') ?></p></div>
        <div><strong>4–6. Acquisitions et intégrité</strong>
            <?php if ($acquisitions === []): ?><p class="muted">Aucune acquisition.</p>
            <?php else: foreach ($acquisitions as $a): ?>
                <p><?= $h($a['reference_code'] ?? '') ?> — <?= $h($a['method_label'] ?? '') ?> — <?= $h($a['status_label'] ?? '') ?> — volume <?= $h($a['volume_label'] ?? '—') ?>
                <?php if (!empty($a['reserves'])): ?><br><em>Réserves : <?= $h($a['reserves']) ?></em><?php endif; ?></p>
            <?php endforeach; endif; ?>
        </div>
        <div><strong>8–11. Éléments remarquables et rapprochements</strong>
            <?php if ($findings === []): ?><p class="muted">Aucun signal.</p>
            <?php else: ?><ul><?php foreach ($findings as $f): ?><li><?= $h($f['title'] ?? '') ?> (<?= $h($f['status_label'] ?? '') ?>)</li><?php endforeach; ?></ul><?php endif; ?>
        </div>
        <div><strong>9. Chronologie (extrait)</strong>
            <ul class="iw-feed"><?php foreach (array_slice($timeline, 0, 15) as $e): ?>
                <li><time><?= $h(substr((string) ($e['event_at'] ?? ''), 0, 16)) ?></time><span><?= $h($e['title'] ?? '') ?></span></li>
            <?php endforeach; ?></ul>
        </div>
        <div><strong>12–14. Analyse, annexes, conclusion</strong>
            <p class="muted">Les signaux ci-dessus ne constituent pas des preuves. Toute consolidation reste une décision humaine. Pièces annexées : journal d’acquisition, empreintes d’intégrité, liste des artefacts.</p>
        </div>
    </div>
</section>
<?php endif; ?>
<?php $sseContent = ob_get_clean(); require dirname(__DIR__) . '/_layout.php';
