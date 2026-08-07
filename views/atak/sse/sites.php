<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $sites */
/** @var array<int, array{rooms:int, rooms_checked:int, seizures:int}> $siteCounts */
/** @var array<string,string> $statuses */
/** @var array<string,string> $types */
$total = count($sites);
$openCount = 0;
foreach ($sites as $s) {
    if (($s['status'] ?? '') !== 'cloture') {
        $openCount++;
    }
}
?>
<div class="breadcrumb">
    Athena / SSE / Renseignement /
    <strong>Sites exploités</strong>
</div>

<div class="page-heading">
    <div>
        <div class="page-heading-overline">Exploitation // Sites sensibles</div>
        <h1>Sites exploités</h1>
        <p>
            Dossiers d’exploitation ouverts sur le terrain : checklist de fouille,
            saisies versées et compte rendu de clôture.
        </p>
    </div>
    <div class="page-reference">
        <strong>Vue // Index des sites</strong>
        Réf. ATH-SSE-SITES
    </div>
</div>

<div class="metrics-grid">
    <div class="metric">
        <div class="metric-label">Sites visibles</div>
        <div class="metric-value"><?= $h(str_pad((string) $total, 3, '0', STR_PAD_LEFT)) ?></div>
        <div class="metric-detail">Registre terrain</div>
    </div>
    <div class="metric">
        <div class="metric-label">En cours</div>
        <div class="metric-value"><?= $h(str_pad((string) $openCount, 3, '0', STR_PAD_LEFT)) ?></div>
        <div class="metric-detail">Non clôturés</div>
    </div>
    <div class="metric">
        <div class="metric-label">Accès</div>
        <div class="metric-value"><?= !empty($canManage) ? 'Gest.' : 'Lect.' ?></div>
        <div class="metric-detail">Niveau de session</div>
    </div>
    <div class="metric">
        <div class="metric-label">Horodatage</div>
        <div class="metric-value"><?= $h(date('H:i')) ?></div>
        <div class="metric-detail">Heure locale</div>
    </div>
</div>

<form class="toolbar" method="get" action="<?= $h(url('atak/sse/sites')) ?>">
    <div class="toolbar-field">
        <label for="status">Statut d’exploitation</label>
        <select id="status" name="status">
            <option value="">Tous les statuts</option>
            <?php foreach ($statuses as $k => $lab): ?>
                <option value="<?= $h($k) ?>" <?= ($filters['status'] ?? '') === $k ? 'selected' : '' ?>><?= $h($lab) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="toolbar-field">
        <label for="site_type">Type de site</label>
        <select id="site_type" name="site_type">
            <option value="">Tous les types</option>
            <?php foreach ($types as $k => $lab): ?>
                <option value="<?= $h($k) ?>" <?= ($filters['site_type'] ?? '') === $k ? 'selected' : '' ?>><?= $h($lab) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="toolbar-actions">
        <button class="btn" type="submit">Appliquer</button>
    </div>
</form>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">03.01</span>
            Registre des sites
        </div>
        <div class="panel-meta">Ouverture depuis le terrain // lecture</div>
    </div>

    <?php if ($sites === []): ?>
        <div class="empty-state">
            <div class="empty-state-inner">
                <div class="empty-symbol">—</div>
                <strong>Aucun site exploité</strong>
                <p>
                    Les sites ouverts depuis le terminal apparaîtront ici, avec leur
                    checklist de fouille et les saisies versées.
                </p>
            </div>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Référence</th>
                    <th>Site</th>
                    <th>Type</th>
                    <th>Statut</th>
                    <th>Fouille</th>
                    <th>Saisies</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($sites as $s):
                    $cnt = $siteCounts[(int) ($s['id'] ?? 0)] ?? ['rooms' => 0, 'rooms_checked' => 0, 'seizures' => 0];
                    $pct = $cnt['rooms'] > 0 ? (int) round(($cnt['rooms_checked'] / $cnt['rooms']) * 100) : 0;
                    $pctClass = $pct >= 100 ? 'is-good' : ($pct >= 50 ? 'is-fair' : '');
                ?>
                    <tr>
                        <td><span class="record-id"><?= $h($s['reference_code'] ?? '') ?></span></td>
                        <td>
                            <span class="record-name"><?= $h($s['name'] ?? '') ?></span>
                            <span class="record-sub">
                                <?php if (!empty($s['grid_reference'])): ?>
                                    grille <?= $h($s['grid_reference']) ?>
                                <?php else: ?>
                                    position non relevée
                                <?php endif; ?>
                            </span>
                        </td>
                        <td><?= $h($s['site_type_label'] ?? '') ?></td>
                        <td><span class="badge"><?= $h($s['status_label'] ?? '') ?></span></td>
                        <td>
                            <span class="sse-score-cell">
                                <span class="sse-gauge <?= $h($pctClass) ?>">
                                    <span style="width: <?= $h((string) $pct) ?>%"></span>
                                </span>
                                <span class="sse-sample-score">
                                    <?= (int) $cnt['rooms_checked'] ?>/<?= (int) $cnt['rooms'] ?>
                                </span>
                            </span>
                        </td>
                        <td class="record-id"><?= (int) $cnt['seizures'] ?></td>
                        <td>
                            <a class="btn-open" href="<?= $h(url('atak/sse/sites/' . (int) ($s['id'] ?? 0))) ?>">Ouvrir</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
