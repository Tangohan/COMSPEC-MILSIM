<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $acquisitions */
require __DIR__ . '/_subnav.php';
?>
<div class="breadcrumb">Athena / SSE / <a class="link" href="<?= $h(url('atak/sse/exploitation-numerique')) ?>">Exploitation numérique</a> / <strong>Acquisitions</strong></div>
<div class="page-heading">
    <div>
        <div class="page-heading-overline">Laboratoire // Acquisitions</div>
        <h1>Acquisitions numériques</h1>
        <p>Copies et extractions liées aux supports — distinctes des fiches matérielles.</p>
    </div>
    <div class="page-reference"><strong>Vue // Acquisitions</strong> ATH-SSE-LABNUM-ACQ</div>
</div>
<form class="toolbar" method="get">
    <div class="toolbar-field">
        <label for="status">Statut</label>
        <select id="status" name="status">
            <option value="">Tous</option>
            <?php foreach ($statuses as $k => $lab): ?>
                <option value="<?= $h($k) ?>" <?= ($filters['status'] ?? '') === $k ? 'selected' : '' ?>><?= $h($lab) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="toolbar-actions"><button class="btn" type="submit">Filtrer</button></div>
</form>
<section class="panel">
    <?php if ($acquisitions === []): ?>
        <div class="empty-state"><div class="empty-state-inner"><strong>Aucune acquisition</strong><p>Lancez une acquisition depuis la fiche d’un support.</p></div></div>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Référence</th><th>Support</th><th>Méthode</th><th>Statut</th><th>Volume</th><th>Artefacts</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($acquisitions as $a): ?>
                <tr>
                    <td><?= $h($a['reference_code'] ?? '') ?></td>
                    <td><?= $h($a['device_reference'] ?? '') ?></td>
                    <td><?= $h($a['method_label'] ?? '') ?></td>
                    <td><?= $h($a['status_label'] ?? '') ?></td>
                    <td><?= $h($a['volume_label'] ?? '—') ?></td>
                    <td><?= (int) ($a['artifact_count'] ?? 0) ?></td>
                    <td><a class="btn-open" href="<?= $h(url('atak/sse/exploitation-numerique/acquisitions/' . (int) $a['id'])) ?>">Ouvrir</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</section>
<?php $sseContent = ob_get_clean(); require dirname(__DIR__) . '/_layout.php';
