<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$j = static fn (mixed $v): string => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
/** @var array<string,mixed> $mesh */
/** @var array<string,mixed>|null $case */
/** @var list<array<string,mixed>> $nodes */
/** @var list<array<string,mixed>> $edges */
/** @var array<string,int> $histogram */
/** @var array<string,string> $kindLabels */
/** @var array<string,string> $relationLabels */
/** @var array<string,string> $reliabilityLabels */
/** @var array<string,string> $statuses */
/** @var array<string,string> $classifications */
/** @var bool $canManage */
$meshId = (int) ($mesh['id'] ?? 0);
$csrf = \App\Core\Csrf::token();
?>
<div class="breadcrumb">
    Athena / SSE /
    <a class="link" href="<?= $h(url('atak/sse/toiles')) ?>">Toiles</a> /
    <strong><?= $h($mesh['reference_code'] ?? '') ?></strong>
</div>

<div class="page-heading">
    <div>
        <div class="page-heading-overline">Toile // Data mesh</div>
        <h1><?= $h($mesh['title'] ?? '') ?></h1>
        <p>
            <?= $h($mesh['classification_label'] ?? '') ?>
            · <?= $h($mesh['status_label'] ?? '') ?>
            <?php if (!empty($case)): ?>
                · Dossier
                <a class="link" href="<?= $h(url('atak/sse/dossiers/' . (int) $case['id'])) ?>"><?= $h($case['reference_code'] ?? '') ?></a>
            <?php endif; ?>
        </p>
    </div>
    <div class="page-reference">
        <strong><?= $h($mesh['reference_code'] ?? '') ?></strong>
        <?= count($nodes) ?> entités · <?= count($edges) ?> liens
    </div>
</div>

<div class="sse-mesh-workspace">
    <div class="sse-mesh-stage panel">
        <div class="panel-header">
            <div class="panel-title"><span class="panel-index">07.10</span> Canevas</div>
            <div class="panel-meta">
                <button type="button" class="btn btn--ghost" id="sse-mesh-relayout">Réorganiser</button>
                <?php if ($canManage): ?>
                    <button type="button" class="btn" id="sse-mesh-save-layout">Enregistrer la disposition</button>
                <?php endif; ?>
            </div>
        </div>
        <div class="sse-mesh-canvas-wrap">
            <svg id="sse-mesh-canvas" class="sse-mesh-canvas" role="img" aria-label="Toile de données"></svg>
            <div class="sse-mesh-hint">Glissez les nœuds · molette pour zoomer · clic sur un nœud pour le sélectionner</div>
        </div>
    </div>

    <aside class="sse-mesh-side">
        <section class="panel">
            <div class="panel-header">
                <div class="panel-title"><span class="panel-index">07.11</span> Répartition</div>
            </div>
            <div class="panel-body sse-mesh-hist">
                <?php if ($histogram === []): ?>
                    <p class="muted">Aucune entité.</p>
                <?php else: ?>
                    <?php
                    $maxH = max(1, max($histogram));
                    foreach ($histogram as $kind => $n):
                        $pct = (int) round(($n / $maxH) * 100);
                        ?>
                        <div class="sse-mesh-hist-row">
                            <span><?= $h($kindLabels[$kind] ?? $kind) ?></span>
                            <i style="--w:<?= $pct ?>%"></i>
                            <b><?= (int) $n ?></b>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <section class="panel" id="sse-mesh-selection">
            <div class="panel-header">
                <div class="panel-title"><span class="panel-index">07.12</span> Sélection</div>
            </div>
            <div class="panel-body">
                <p class="muted" id="sse-mesh-sel-empty">Cliquez une entité sur le canevas.</p>
                <div id="sse-mesh-sel-body" hidden>
                    <div class="sse-block-title" id="sse-mesh-sel-kind"></div>
                    <strong id="sse-mesh-sel-label"></strong>
                    <p class="muted" id="sse-mesh-sel-detail"></p>
                    <p class="sse-mesh-sel-links muted" id="sse-mesh-sel-links"></p>
                </div>
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div class="panel-title"><span class="panel-index">07.12b</span> Filtres graphe</div>
            </div>
            <div class="panel-body iw-graph-filters">
                <label>Profondeur
                    <select id="sse-mesh-depth" data-mesh-filter="depth">
                        <option value="1">1 saut</option>
                        <option value="2" selected>2 sauts</option>
                        <option value="3">3 sauts</option>
                    </select>
                </label>
                <label>Confiance minimale
                    <select id="sse-mesh-minrel" data-mesh-filter="reliability">
                        <option value="any" selected>Tous les liens</option>
                        <option value="corroborated">Corroborés et plus</option>
                        <option value="confirmed">Confirmés seulement</option>
                    </select>
                </label>
                <label>Catégorie
                    <select id="sse-mesh-kind" data-mesh-filter="kind">
                        <option value="any" selected>Toutes</option>
                        <?php foreach ($kindLabels as $k => $lab): ?>
                            <option value="<?= $h($k) ?>"><?= $h($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="iw-check"><input type="checkbox" id="sse-mesh-anomalies-only" data-mesh-filter="anomalies"> Uniquement les anomalies</label>
                <p class="sse-note">Les filtres guident l’exploration ; ils ne modifient pas les données enregistrées.</p>
            </div>
        </section>

        <?php if ($canManage): ?>
        <section class="panel">
            <div class="panel-header">
                <div class="panel-title"><span class="panel-index">07.13</span> Ajouter une entité</div>
            </div>
            <div class="panel-body">
                <form method="post" action="<?= $h(url('atak/sse/toiles/' . $meshId . '/entites')) ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="pos_x" id="sse-mesh-new-x" value="360">
                    <input type="hidden" name="pos_y" id="sse-mesh-new-y" value="240">
                    <label for="kind">Type</label>
                    <select id="kind" name="kind">
                        <?php foreach ($kindLabels as $k => $lab): ?>
                            <option value="<?= $h($k) ?>" <?= $k === 'person' ? 'selected' : '' ?>><?= $h($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="label">Libellé</label>
                    <input id="label" name="label" type="text" required maxlength="200" placeholder="Nom ou désignation">
                    <label for="detail">Précision</label>
                    <input id="detail" name="detail" type="text" maxlength="255" placeholder="Rôle, immatriculation…">
                    <button class="btn" type="submit">Ajouter</button>
                </form>
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div class="panel-title"><span class="panel-index">07.14</span> Poser un lien</div>
            </div>
            <div class="panel-body">
                <?php if (count($nodes) < 2): ?>
                    <p class="muted">Ajoutez au moins deux entités.</p>
                <?php else: ?>
                    <form method="post" action="<?= $h(url('atak/sse/toiles/' . $meshId . '/liens')) ?>">
                        <?= \App\Core\Csrf::field() ?>
                        <label for="from_node_id">De</label>
                        <select id="from_node_id" name="from_node_id" required>
                            <?php foreach ($nodes as $n): ?>
                                <option value="<?= (int) $n['id'] ?>"><?= $h(($n['kind_label'] ?? '') . ' — ' . ($n['label'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="relation">Nature</label>
                        <select id="relation" name="relation">
                            <?php foreach ($relationLabels as $k => $lab): ?>
                                <option value="<?= $h($k) ?>"><?= $h($lab) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="to_node_id">Vers</label>
                        <select id="to_node_id" name="to_node_id" required>
                            <?php foreach ($nodes as $n): ?>
                                <option value="<?= (int) $n['id'] ?>"><?= $h(($n['kind_label'] ?? '') . ' — ' . ($n['label'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="reliability">Fiabilité</label>
                        <select id="reliability" name="reliability">
                            <?php foreach ($reliabilityLabels as $k => $lab): ?>
                                <option value="<?= $h($k) ?>"><?= $h($lab) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="note">Sur quoi repose le lien</label>
                        <input id="note" name="note" type="text" maxlength="255">
                        <button class="btn" type="submit">Enregistrer le lien</button>
                    </form>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>
    </aside>
</div>

<?php if ($canManage): ?>
<section class="panel" style="margin-top:1rem">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">07.15</span> Propriétés de la toile</div>
    </div>
    <div class="panel-body">
        <form method="post" action="<?= $h(url('atak/sse/toiles/' . $meshId)) ?>" class="grid-2">
            <?= \App\Core\Csrf::field() ?>
            <div>
                <label for="mesh_title">Intitulé</label>
                <input id="mesh_title" name="title" type="text" required value="<?= $h($mesh['title'] ?? '') ?>">
            </div>
            <div>
                <label for="mesh_status">État</label>
                <select id="mesh_status" name="status">
                    <?php foreach ($statuses as $k => $lab): ?>
                        <option value="<?= $h($k) ?>" <?= ($mesh['status'] ?? '') === $k ? 'selected' : '' ?>><?= $h($lab) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="mesh_class">Diffusion</label>
                <select id="mesh_class" name="classification">
                    <?php foreach ($classifications as $k => $lab): ?>
                        <option value="<?= $h($k) ?>" <?= ($mesh['classification'] ?? '') === $k ? 'selected' : '' ?>><?= $h($lab) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="mesh_summary">Objet</label>
                <textarea id="mesh_summary" name="summary"><?= $h($mesh['summary'] ?? '') ?></textarea>
            </div>
            <div style="grid-column:1/-1">
                <button class="btn" type="submit">Enregistrer</button>
            </div>
        </form>
    </div>
</section>

<?php if ($edges !== []): ?>
<section class="panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">07.16</span> Liens enregistrés</div>
    </div>
    <ul class="sse-edges">
        <?php
        $byId = [];
        foreach ($nodes as $n) {
            $byId[(int) $n['id']] = $n;
        }
        foreach ($edges as $e):
            $from = $byId[(int) $e['from_node_id']] ?? null;
            $to = $byId[(int) $e['to_node_id']] ?? null;
            ?>
            <li class="sse-edge is-posed">
                <span class="sse-edge-from"><?= $h($from['label'] ?? '?') ?></span>
                <span class="sse-edge-rel"><?= $h($e['relation_label'] ?? '') ?></span>
                <span class="sse-edge-to"><?= $h($to['label'] ?? '?') ?></span>
                <span class="sse-edge-tags">
                    <form method="post" action="<?= $h(url('atak/sse/toiles/' . $meshId . '/liens/' . (int) $e['id'] . '/supprimer')) ?>" onsubmit="return confirm('Retirer ce lien ?');">
                        <?= \App\Core\Csrf::field() ?>
                        <button class="btn btn--danger" type="submit">Retirer</button>
                    </form>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>
<?php endif; ?>

<script>
window.SSE_MESH = {
  meshId: <?= (int) $meshId ?>,
  canManage: <?= $canManage ? 'true' : 'false' ?>,
  csrf: <?= $j($csrf) ?>,
  layoutUrl: <?= $j(url('atak/sse/toiles/' . $meshId . '/disposition')) ?>,
  nodes: <?= $j($nodes) ?>,
  edges: <?= $j($edges) ?>,
  kindLabels: <?= $j($kindLabels) ?>
};
</script>
<script src="<?= $h(asset_url('assets/js/sse-mesh.js')) ?>?v=202608051700"></script>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
