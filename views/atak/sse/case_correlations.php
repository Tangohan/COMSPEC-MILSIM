<?php
declare(strict_types=1);

use App\Services\Sse\SseCorrelationService as Corr;

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array<string,mixed> $case */
/** @var array<string,array<string,mixed>> $nodes */
/** @var list<array<string,mixed>> $edges */
/** @var list<array<string,mixed>> $stored */
/** @var array<string,string> $relationLabels */
/** @var array<string,string> $reliabilityLabels */

$caseId = (int) ($case['id'] ?? 0);

$typeLabels = is_array($nodeTypeLabels ?? null) && $nodeTypeLabels !== []
    ? $nodeTypeLabels
    : [
        'person' => 'Personne',
        'site' => 'Site',
        'room' => 'Pièce',
        'seizure' => 'Saisie',
        'evidence' => 'Pièce à conviction',
        'document' => 'Document',
    ];

// Regroupement par nature pour les listes déroulantes : tout élément du dossier
// peut être relié à tout autre, on ne préjuge pas de la combinaison utile.
$groupTitles = [
    'person' => 'Personnes',
    'site' => 'Sites',
    'room' => 'Pièces',
    'seizure' => 'Saisies',
    'evidence' => 'Pièces à conviction',
    'document' => 'Documents',
];
$grouped = [];
foreach ($nodes as $key => $n) {
    $grouped[(string) ($n['type'] ?? 'autre')][$key] = $n;
}
$orderedGroups = [];
foreach (array_keys($groupTitles) as $type) {
    if (!empty($grouped[$type])) {
        $orderedGroups[$type] = $grouped[$type];
    }
}
foreach ($grouped as $type => $list) {
    if (!isset($orderedGroups[$type])) {
        $orderedGroups[$type] = $list;
    }
}

$node = static function (string $type, int $id) use ($nodes): ?array {
    return $nodes[$type . ':' . $id] ?? null;
};

$designation = static function (?array $n, string $type, int $id) use ($typeLabels): string {
    if ($n === null) {
        return ($typeLabels[$type] ?? 'Élément') . ' hors dossier';
    }
    $ref = trim((string) ($n['ref'] ?? ''));
    $label = trim((string) ($n['label'] ?? ''));

    return $ref !== '' ? $ref . ' — ' . $label : $label;
};

$bySource = static fn (string $src): array => array_values(
    array_filter($edges, static fn (array $e): bool => ($e['source'] ?? '') === $src)
);
$derived = $bySource(Corr::SOURCE_DERIVED);
$byRule = $bySource(Corr::SOURCE_RULE);
$posed = $bySource(Corr::SOURCE_ANALYST);

// Degré : combien d'arêtes touchent chaque nœud. Un sujet très relié mérite
// d'être regardé avant les autres, c'est la seule hiérarchie que la page impose.
// Amorcé à zéro sur tous les nœuds : une personne rattachée au dossier mais encore
// reliée à rien doit rester visible, c'est justement celle qu'il faut aller creuser.
$degree = array_fill_keys(array_keys($nodes), 0);
foreach ($edges as $e) {
    foreach ([['from_type', 'from_id'], ['to_type', 'to_id']] as [$tk, $ik]) {
        $k = $e[$tk] . ':' . $e[$ik];
        $degree[$k] = ($degree[$k] ?? 0) + 1;
    }
}
arsort($degree);
?>
<div class="breadcrumb">
    Athena / SSE /
    <a class="link" href="<?= $h(url('atak/sse/dossiers')) ?>">Dossiers</a> /
    <a class="link" href="<?= $h(url('atak/sse/dossiers/' . $caseId)) ?>"><?= $h($case['reference_code'] ?? '') ?></a> /
    <strong>Corrélations</strong>
</div>

<div class="page-heading">
    <div>
        <div class="page-heading-overline">Exploitation // Mise en relation</div>
        <h1>Corrélations du dossier</h1>
        <p>
            Ce qui relie les personnes, les sites, les pièces, les saisies, les pièces à
            conviction et les documents du dossier.
            Les liens <strong>déduits</strong> sont recalculés à chaque ouverture depuis les
            saisies déjà enregistrées : corriger une fiche corrige le graphe. Les liens
            <strong>automatiques</strong> sont proposés par une règle et attendent votre
            vérification. Les liens <strong>d’analyste</strong> sont vos hypothèses, et
            restent signalés comme telles.
        </p>
    </div>
    <div class="page-reference">
        <strong><?= $h($case['reference_code'] ?? '') ?></strong>
        <?= $h($case['title'] ?? '') ?>
    </div>
</div>

<div class="security-notice">
    <div class="security-notice-code">SEC-09</div>
    <div>
        <strong>Une relation n’est pas une preuve</strong>
        <span>
            Un lien affiché ici décrit une proximité constatée dans le dossier. Il ne
            vaut ni appartenance, ni implication, ni identification.
        </span>
    </div>
</div>

<div class="metrics-grid">
    <div class="metric">
        <div class="metric-label">Entités</div>
        <div class="metric-value"><?= $h(str_pad((string) count($nodes), 3, '0', STR_PAD_LEFT)) ?></div>
        <div class="metric-detail">Nœuds du graphe</div>
    </div>
    <div class="metric">
        <div class="metric-label">Liens déduits</div>
        <div class="metric-value"><?= $h(str_pad((string) count($derived), 3, '0', STR_PAD_LEFT)) ?></div>
        <div class="metric-detail">Issus des saisies</div>
    </div>
    <div class="metric">
        <div class="metric-label">Liens automatiques</div>
        <div class="metric-value"><?= $h(str_pad((string) count($byRule), 3, '0', STR_PAD_LEFT)) ?></div>
        <div class="metric-detail">Posés par une règle, à vérifier</div>
    </div>
    <div class="metric">
        <div class="metric-label">Liens d’analyste</div>
        <div class="metric-value"><?= $h(str_pad((string) count($posed), 3, '0', STR_PAD_LEFT)) ?></div>
        <div class="metric-detail">Hypothèses assumées</div>
    </div>
</div>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">01.07</span>
            Entités du dossier
        </div>
        <div class="panel-meta">Classées par nombre de liens</div>
    </div>

    <?php if ($nodes === []): ?>
        <div class="panel-body">
            <p class="muted">
                Aucun élément rattaché. Rattachez des personnes, un site exploité, des pièces
                à conviction ou un document au dossier : les liens apparaîtront d’eux-mêmes.
            </p>
        </div>
    <?php else: ?>
        <div class="sse-graph">
            <?php foreach (array_keys($degree) as $key): ?>
                <?php
                $n = $nodes[$key] ?? null;
                if ($n === null) {
                    continue;
                }
                ?>
                <article class="sse-node is-<?= $h($n['type'] ?? '') ?>">
                    <div class="sse-node-head">
                        <span class="sse-node-ref"><?= $h(($n['ref'] ?? '') !== '' ? $n['ref'] : '—') ?></span>
                        <span class="sse-node-kind"><?= $h($typeLabels[$n['type'] ?? ''] ?? 'Élément') ?></span>
                    </div>
                    <div class="sse-node-label"><?= $h($n['label'] ?? '') ?></div>
                    <?php if (($n['detail'] ?? '') !== ''): ?>
                        <div class="sse-node-detail"><?= $h($n['detail']) ?></div>
                    <?php endif; ?>
                    <div class="sse-node-degree"><?= (int) ($degree[$key] ?? 0) ?> lien<?= ($degree[$key] ?? 0) > 1 ? 's' : '' ?></div>
                    <?php if (($n['url'] ?? '') !== ''): ?>
                        <a class="btn-open" href="<?= $h($n['url']) ?>">Ouvrir</a>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">01.08</span>
            Chaîne de liens
        </div>
        <div class="panel-meta"><?= count($edges) ?> relation<?= count($edges) > 1 ? 's' : '' ?></div>
    </div>

    <?php if ($edges === []): ?>
        <div class="panel-body">
            <p class="muted">Aucun lien à afficher pour l’instant.</p>
        </div>
    <?php else: ?>
        <ul class="sse-edges">
            <?php foreach ($edges as $e): ?>
                <?php
                $from = $node((string) $e['from_type'], (int) $e['from_id']);
                $to = $node((string) $e['to_type'], (int) $e['to_id']);
                $src = (string) ($e['source'] ?? Corr::SOURCE_DERIVED);
                $srcClass = match ($src) {
                    Corr::SOURCE_RULE => 'is-rule',
                    Corr::SOURCE_ANALYST => 'is-posed',
                    default => 'is-derived',
                };
                ?>
                <li class="sse-edge <?= $h($srcClass) ?>">
                    <span class="sse-edge-from"><?= $h($designation($from, (string) $e['from_type'], (int) $e['from_id'])) ?></span>
                    <span class="sse-edge-rel"><?= $h($e['relation_label'] ?? '') ?></span>
                    <span class="sse-edge-to"><?= $h($designation($to, (string) $e['to_type'], (int) $e['to_id'])) ?></span>
                    <span class="sse-edge-tags">
                        <span class="badge"><?= $h($e['source_label'] ?? Corr::SOURCE_LABELS[Corr::SOURCE_DERIVED]) ?></span>
                        <span class="badge is-<?= $h($e['reliability'] ?? 'unverified') ?>"><?= $h($e['reliability_label'] ?? '') ?></span>
                    </span>
                    <?php if (($e['note'] ?? '') !== ''): ?>
                        <span class="sse-edge-note"><?= $h($e['note']) ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php if (!empty($canManage)): ?>
<section class="panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">01.09</span>
            Poser un lien
        </div>
        <div class="panel-meta">Entre deux éléments du dossier</div>
    </div>
    <div class="panel-body">
        <?php if (count($nodes) < 2): ?>
            <p class="muted">
                Il faut au moins deux éléments dans le dossier — personnes, site, pièce,
                saisie, pièce à conviction ou document — pour poser un lien.
            </p>
        <?php else: ?>
            <p class="muted" style="margin:0 0 .9rem">
                Personnes, sites, pièces, saisies, pièces à conviction et documents du dossier
                peuvent tous être reliés entre eux, dans n’importe quelle combinaison.
            </p>
            <form method="post" action="<?= $h(url('atak/sse/dossiers/' . $caseId . '/correlations')) ?>" class="sse-relation-form">
                <?= \App\Core\Csrf::field() ?>

                <div class="field">
                    <label for="from">Premier élément</label>
                    <select id="from" name="from" required>
                        <?php foreach ($orderedGroups as $type => $list): ?>
                            <optgroup label="<?= $h($groupTitles[$type] ?? ($typeLabels[$type] ?? 'Éléments')) ?>">
                                <?php foreach ($list as $key => $n): ?>
                                    <option value="<?= $h($key) ?>">
                                        <?= $h($designation($n, (string) $type, (int) ($n['id'] ?? 0))) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="relation">Nature du lien</label>
                    <select id="relation" name="relation">
                        <?php foreach ($relationLabels as $key => $label): ?>
                            <option value="<?= $h($key) ?>" <?= $key === 'associe' ? 'selected' : '' ?>><?= $h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="to">Second élément</label>
                    <select id="to" name="to" required>
                        <?php foreach ($orderedGroups as $type => $list): ?>
                            <optgroup label="<?= $h($groupTitles[$type] ?? ($typeLabels[$type] ?? 'Éléments')) ?>">
                                <?php foreach ($list as $key => $n): ?>
                                    <option value="<?= $h($key) ?>">
                                        <?= $h($designation($n, (string) $type, (int) ($n['id'] ?? 0))) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="reliability">Fiabilité</label>
                    <select id="reliability" name="reliability">
                        <?php foreach ($reliabilityLabels as $key => $label): ?>
                            <option value="<?= $h($key) ?>"><?= $h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field field--wide">
                    <label for="note">Sur quoi repose ce lien</label>
                    <input type="text" id="note" name="note" maxlength="255"
                           placeholder="Déclaration recueillie, objet commun, présence simultanée…">
                </div>

                <button class="btn" type="submit">Enregistrer le lien</button>
            </form>
        <?php endif; ?>
    </div>
</section>

<?php if ($stored !== []): ?>
<section class="panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">01.10</span>
            Liens posés
        </div>
        <div class="panel-meta">Retirables</div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Premier élément</th>
                <th>Lien</th>
                <th>Second élément</th>
                <th>Fiabilité</th>
                <th>Posée par</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($stored as $r): ?>
                <tr>
                    <td><?= $h($designation($node((string) $r['from_type'], (int) $r['from_id']), (string) $r['from_type'], (int) $r['from_id'])) ?></td>
                    <td><span class="badge"><?= $h(Corr::relationLabel((string) $r['relation'])) ?></span></td>
                    <td><?= $h($designation($node((string) $r['to_type'], (int) $r['to_id']), (string) $r['to_type'], (int) $r['to_id'])) ?></td>
                    <td><?= $h(Corr::reliabilityLabel((string) ($r['reliability'] ?? ''))) ?></td>
                    <td class="record-id"><?= $h($r['author_label'] ?? '—') ?></td>
                    <td>
                        <form method="post"
                              action="<?= $h(url('atak/sse/dossiers/' . $caseId . '/correlations/' . (int) $r['id'] . '/supprimer')) ?>">
                            <?= \App\Core\Csrf::field() ?>
                            <button class="btn btn--ghost btn--sm" type="submit">Retirer</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>
<?php endif; ?>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
