<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array{person:array,matches:list}> $matches */
/** @var list<array<string,mixed>> $entries */
/** @var bool $canManage */
$matchRows = 0;
foreach ($matches as $row) {
    $matchRows += count($row['matches'] ?? []);
}
?>
<div class="breadcrumb">
    Athena / SSE / Renseignement /
    <strong>Croisements</strong>
</div>

<div class="page-heading">
    <div>
        <div class="page-heading-overline">Surveillance // Correspondances</div>
        <h1>Croisements</h1>
        <p>
            Correspondances probables entre fiches terrain et listes de surveillance
            de la communauté.
        </p>
    </div>
    <div class="page-reference">
        <strong>Vue // Croisements</strong>
        Réf. ATH-SSE-CROISEMENTS
    </div>
</div>

<div class="metrics-grid">
    <div class="metric">
        <div class="metric-label">Correspondances</div>
        <div class="metric-value"><?= $h(str_pad((string) $matchRows, 3, '0', STR_PAD_LEFT)) ?></div>
        <div class="metric-detail">Détectées</div>
    </div>
    <div class="metric">
        <div class="metric-label">Liste active</div>
        <div class="metric-value"><?= $h(str_pad((string) count($entries), 3, '0', STR_PAD_LEFT)) ?></div>
        <div class="metric-detail">Entrées surveillées</div>
    </div>
    <div class="metric">
        <div class="metric-label">Gestion</div>
        <div class="metric-value"><?= $canManage ? 'Oui' : 'Non' ?></div>
        <div class="metric-detail">Édition listes</div>
    </div>
    <div class="metric">
        <div class="metric-label">Horodatage</div>
        <div class="metric-value"><?= $h(date('H:i')) ?></div>
        <div class="metric-detail">Heure locale</div>
    </div>
</div>

<?php if ($canManage): ?>
<section class="panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">03.01</span>
            Ajouter une entrée surveillée
        </div>
    </div>
    <div class="panel-body">
        <form method="post" action="<?= $h(url('atak/sse/croisements/watchlist')) ?>">
            <?= \App\Core\Csrf::field() ?>
            <div class="grid-2">
                <div>
                    <label for="last_name">Nom</label>
                    <input id="last_name" name="last_name" type="text" required>
                </div>
                <div>
                    <label for="first_name">Prénom</label>
                    <input id="first_name" name="first_name" type="text">
                </div>
            </div>
            <label for="alias">Alias</label>
            <input id="alias" name="alias" type="text">
            <label for="threat_level">Niveau</label>
            <select id="threat_level" name="threat_level">
                <option value="surveillance">Surveillance</option>
                <option value="prioritaire">Personne prioritaire</option>
            </select>
            <label for="notes">Notes</label>
            <textarea id="notes" name="notes"></textarea>
            <button class="btn" type="submit">Enregistrer</button>
        </form>
    </div>
</section>
<?php endif; ?>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">03.02</span>
            Correspondances détectées
        </div>
        <div class="panel-meta">Score de similarité</div>
    </div>
    <?php if ($matches === []): ?>
        <div class="empty-state">
            <div class="empty-state-inner">
                <div class="empty-symbol">—</div>
                <strong>Aucune correspondance</strong>
                <p>Aucune correspondance significative pour le moment.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Fiche terrain</th>
                    <th>Entrée surveillée</th>
                    <th>Score</th>
                    <th>Motif</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($matches as $row): ?>
                    <?php foreach ($row['matches'] as $m): ?>
                        <tr>
                            <td><span class="record-name"><?= $h($row['person']['display_name'] ?? '') ?></span></td>
                            <td>
                                <span class="record-name"><?= $h($m['entry']['display_name'] ?? '') ?></span>
                                <span class="record-sub"><?= $h($m['entry']['threat_level_label'] ?? '') ?></span>
                            </td>
                            <td class="record-id"><?= (int) $m['score'] ?> %</td>
                            <td><?= $h($m['reason'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">03.03</span>
            Liste de surveillance active
        </div>
    </div>
    <?php if ($entries === []): ?>
        <div class="panel-body"><p class="muted">Aucune entrée.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Identité</th>
                    <th>Niveau</th>
                    <th>Notes</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($entries as $e): ?>
                    <tr>
                        <td>
                            <span class="record-name"><?= $h($e['display_name']) ?></span>
                            <?php if (!empty($e['alias'])): ?>
                                <span class="record-sub"><?= $h($e['alias']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge"><?= $h($e['threat_level_label']) ?></span></td>
                        <td class="muted"><?= $h($e['notes'] ?? '—') ?></td>
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
