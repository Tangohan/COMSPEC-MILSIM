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
$nodeCount = count($nodes);
$edgeCount = count($edges);
$byId = [];
foreach ($nodes as $n) {
    $byId[(int) ($n['id'] ?? 0)] = $n;
}
$statusKey = (string) ($mesh['status'] ?? '');
$classKey = (string) ($mesh['classification'] ?? '');
$classBadge = match ($classKey) {
    'confidentiel' => 'badge badge--amber',
    'tres_restreint' => 'badge badge--red',
    'interne' => 'badge badge--gray',
    default => 'badge',
};
$kindColors = [
    'person' => '#3ddc9a',
    'site' => '#6bb2f0',
    'event' => '#e0a233',
    'document' => '#a78bfa',
    'vehicle' => '#38bdf8',
    'weapon' => '#ff6b5e',
    'phone' => '#f472b6',
    'organization' => '#94a3b8',
    'seizure' => '#fbbf24',
    'alias' => '#86efac',
    'biometric' => '#2dd4bf',
    'photo' => '#c084fc',
    'terminal' => '#67e8f9',
    'report' => '#fdba74',
    'custom' => '#7dd3fc',
];
?>
<div class="breadcrumb">
    Athena / SSE /
    <a class="link" href="<?= $h(url('atak/sse/toiles')) ?>">Investigations</a> /
    <strong><?= $h($mesh['reference_code'] ?? '') ?></strong>
</div>

<div class="page-heading sse-mesh-heading">
    <div>
        <div class="page-heading-overline">Investigation // Graphe relationnel</div>
        <h1><?= $h($mesh['title'] ?? '') ?></h1>
        <p class="sse-mesh-heading-meta">
            <span class="<?= $h($classBadge) ?>"><?= $h($mesh['classification_label'] ?? '') ?></span>
            <span class="badge"><?= $h($mesh['status_label'] ?? '') ?></span>
            <?php if (!empty($case)): ?>
                <a class="sse-mesh-case-chip" href="<?= $h(url('atak/sse/dossiers/' . (int) $case['id'])) ?>">
                    Dossier <?= $h($case['reference_code'] ?? '') ?>
                </a>
            <?php endif; ?>
        </p>
        <?php if (!empty($mesh['summary'])): ?>
            <p class="sse-mesh-summary"><?= $h($mesh['summary']) ?></p>
        <?php endif; ?>
    </div>
    <div class="page-reference sse-mesh-kpis">
        <div class="sse-mesh-kpi">
            <span class="sse-mesh-kpi-label">Référence</span>
            <strong><?= $h($mesh['reference_code'] ?? '') ?></strong>
        </div>
        <div class="sse-mesh-kpi">
            <span class="sse-mesh-kpi-label">Entités</span>
            <strong><?= $nodeCount ?></strong>
        </div>
        <div class="sse-mesh-kpi">
            <span class="sse-mesh-kpi-label">Liens</span>
            <strong><?= $edgeCount ?></strong>
        </div>
    </div>
</div>

<div class="sse-mesh-workspace">
    <div class="sse-mesh-stage panel">
        <div class="panel-header sse-mesh-stage-head">
            <div class="panel-title"><span class="panel-index">01</span> Canevas</div>
            <div class="panel-meta sse-mesh-toolbar">
                <?php if ($canManage): ?>
                    <button type="button" class="btn btn--ghost btn--sm" id="sse-mesh-link-mode"
                            aria-pressed="false" title="Relier deux entités en cliquant l’une puis l’autre">Relier deux entités</button>
                <?php endif; ?>
                <button type="button" class="btn btn--ghost btn--sm" id="sse-mesh-relayout" title="Réorganiser automatiquement">Réorganiser</button>
                <button type="button" class="btn btn--ghost btn--sm" id="sse-mesh-fit" title="Recadrer la vue">Recadrer</button>
                <?php if ($canManage): ?>
                    <button type="button" class="btn btn--sm" id="sse-mesh-save-layout">Enregistrer la disposition</button>
                <?php endif; ?>
            </div>
        </div>

        <div class="sse-mesh-canvas-wrap">
            <?php if ($nodeCount === 0): ?>
                <div class="sse-mesh-empty">
                    <strong>Toile vide</strong>
                    <p>Ajoutez une première entité dans le panneau « Construire », puis posez des liens entre les éléments.</p>
                </div>
            <?php endif; ?>
            <svg id="sse-mesh-canvas" class="sse-mesh-canvas" role="img" aria-label="Graphe de l’investigation"></svg>
            <div class="sse-mesh-legend" aria-hidden="true">
                <?php
                $legendKinds = array_keys($histogram);
                if ($legendKinds === []) {
                    $legendKinds = array_slice(array_keys($kindLabels), 0, 6);
                }
                foreach (array_slice($legendKinds, 0, 8) as $kind):
                    $color = $kindColors[$kind] ?? '#7dd3fc';
                    ?>
                    <span class="sse-mesh-legend-item">
                        <i style="background:<?= $h($color) ?>"></i>
                        <?= $h($kindLabels[$kind] ?? $kind) ?>
                    </span>
                <?php endforeach; ?>
            </div>
            <div class="sse-mesh-hint" id="sse-mesh-hint">
                Glisser les nœuds · molette pour zoomer · clic pour sélectionner · clic droit pour les actions<?= $canManage ? ' · Maj + glisser d’une entité vers une autre pour les relier' : '' ?>
            </div>

            <?php if ($canManage): ?>
            <form class="sse-mesh-linkbox" id="sse-mesh-linkbox" method="post"
                  action="<?= $h(url('atak/sse/toiles/' . $meshId . '/liens')) ?>" hidden>
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="from_node_id" id="sse-mesh-link-from">
                <input type="hidden" name="to_node_id" id="sse-mesh-link-to">
                <p class="sse-mesh-linkbox__title">Nouveau lien</p>
                <p class="sse-mesh-linkbox__pair">
                    <strong id="sse-mesh-link-from-label"></strong>
                    <span>vers</span>
                    <strong id="sse-mesh-link-to-label"></strong>
                </p>
                <label for="sse-mesh-link-relation">Nature du lien</label>
                <select id="sse-mesh-link-relation" name="relation">
                    <?php foreach ($relationLabels as $k => $lab): ?>
                        <option value="<?= $h($k) ?>"><?= $h($lab) ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="sse-mesh-link-reliability">Fiabilité</label>
                <select id="sse-mesh-link-reliability" name="reliability">
                    <?php foreach ($reliabilityLabels as $k => $lab): ?>
                        <option value="<?= $h($k) ?>"><?= $h($lab) ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="sse-mesh-link-note">Sur quoi repose le lien</label>
                <input type="text" id="sse-mesh-link-note" name="note" maxlength="255"
                       placeholder="Observation, document, témoignage…">
                <div class="sse-mesh-linkbox__actions">
                    <button type="button" class="btn btn--ghost btn--sm" id="sse-mesh-link-cancel">Annuler</button>
                    <button type="submit" class="btn btn--sm">Enregistrer le lien</button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <aside class="sse-mesh-side panel">
        <div class="sse-mesh-tabs" role="tablist" aria-label="Panneau investigation">
            <button type="button" class="sse-mesh-tab is-active" data-mesh-tab="explore" role="tab" aria-selected="true">Explorer</button>
            <?php if ($canManage): ?>
                <button type="button" class="sse-mesh-tab" data-mesh-tab="build" role="tab" aria-selected="false">Construire</button>
                <button type="button" class="sse-mesh-tab" data-mesh-tab="settings" role="tab" aria-selected="false">Paramètres</button>
            <?php else: ?>
                <button type="button" class="sse-mesh-tab" data-mesh-tab="links" role="tab" aria-selected="false">Liens</button>
            <?php endif; ?>
        </div>

        <div class="sse-mesh-tabpanels">
            <div class="sse-mesh-tabpanel is-active" data-mesh-panel="explore" role="tabpanel">
                <section class="sse-mesh-block">
                    <h3 class="sse-mesh-block-title">Sélection</h3>
                    <div id="sse-mesh-selection" class="sse-mesh-selection">
                        <p class="muted" id="sse-mesh-sel-empty">Cliquez une entité sur le canevas pour afficher sa fiche.</p>
                        <div id="sse-mesh-sel-body" class="sse-mesh-sel-card" hidden>
                            <div class="sse-mesh-sel-kind" id="sse-mesh-sel-kind"></div>
                            <strong class="sse-mesh-sel-label" id="sse-mesh-sel-label"></strong>
                            <p class="muted" id="sse-mesh-sel-detail"></p>
                            <div class="sse-mesh-sel-image" id="sse-mesh-sel-image" hidden>
                                <img id="sse-mesh-sel-image-img" alt="Image jointe à l’objet">
                                <p class="muted sse-mesh-sel-image-fallback" id="sse-mesh-sel-image-fallback" hidden></p>
                            </div>
                            <p class="sse-mesh-sel-links" id="sse-mesh-sel-links"></p>
                            <ul class="sse-mesh-sel-edge-list" id="sse-mesh-sel-edge-list"></ul>
                        </div>
                    </div>
                </section>

                <section class="sse-mesh-block">
                    <h3 class="sse-mesh-block-title">Répartition</h3>
                    <div class="sse-mesh-hist">
                        <?php if ($histogram === []): ?>
                            <p class="muted">Aucune entité pour le moment.</p>
                        <?php else: ?>
                            <?php
                            $maxH = max(1, max($histogram));
                            foreach ($histogram as $kind => $n):
                                $pct = (int) round(($n / $maxH) * 100);
                                $color = $kindColors[$kind] ?? 'var(--green)';
                                ?>
                                <div class="sse-mesh-hist-row">
                                    <span><?= $h($kindLabels[$kind] ?? $kind) ?></span>
                                    <i style="--w:<?= $pct ?>%; --c:<?= $h($color) ?>"></i>
                                    <b><?= (int) $n ?></b>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="sse-mesh-block">
                    <h3 class="sse-mesh-block-title">Filtres d’exploration</h3>
                    <div class="sse-mesh-filters">
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
                        <label class="sse-mesh-check">
                            <input type="checkbox" id="sse-mesh-anomalies-only" data-mesh-filter="anomalies">
                            Uniquement les anomalies
                        </label>
                        <p class="sse-note">Les filtres guident la lecture ; ils ne modifient pas les données enregistrées.</p>
                    </div>
                </section>
            </div>

            <?php if ($canManage): ?>
            <div class="sse-mesh-tabpanel" data-mesh-panel="build" role="tabpanel" hidden>
                <section class="sse-mesh-block">
                    <h3 class="sse-mesh-block-title">Ajouter une entité</h3>
                    <form method="post" action="<?= $h(url('atak/sse/toiles/' . $meshId . '/entites')) ?>" class="sse-mesh-form">
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
                        <input id="label" name="label" type="text" required maxlength="200" placeholder="Nom ou désignation" autocomplete="off">
                        <label for="detail">Précision</label>
                        <input id="detail" name="detail" type="text" maxlength="255" placeholder="Rôle, immatriculation, lieu…" autocomplete="off">
                        <button class="btn" type="submit">Ajouter à la toile</button>
                    </form>
                </section>

                <section class="sse-mesh-block">
                    <h3 class="sse-mesh-block-title">Poser un lien</h3>
                    <?php if ($nodeCount < 2): ?>
                        <p class="muted">Ajoutez au moins deux entités avant de créer un lien.</p>
                    <?php else: ?>
                        <form method="post" action="<?= $h(url('atak/sse/toiles/' . $meshId . '/liens')) ?>" class="sse-mesh-form">
                            <?= \App\Core\Csrf::field() ?>
                            <label for="from_node_id">De</label>
                            <select id="from_node_id" name="from_node_id" required>
                                <?php foreach ($nodes as $n): ?>
                                    <option value="<?= (int) $n['id'] ?>"><?= $h(($n['kind_label'] ?? '') . ' — ' . ($n['label'] ?? '')) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label for="relation">Nature du lien</label>
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
                            <input id="note" name="note" type="text" maxlength="255" placeholder="Observation, document, témoignage…">
                            <button class="btn" type="submit">Enregistrer le lien</button>
                        </form>
                    <?php endif; ?>
                </section>

                <?php if ($edges !== []): ?>
                <section class="sse-mesh-block">
                    <h3 class="sse-mesh-block-title">Liens enregistrés <span class="sse-mesh-count"><?= $edgeCount ?></span></h3>
                    <ul class="sse-mesh-edge-list">
                        <?php foreach ($edges as $e):
                            $from = $byId[(int) ($e['from_node_id'] ?? 0)] ?? null;
                            $to = $byId[(int) ($e['to_node_id'] ?? 0)] ?? null;
                            ?>
                            <li class="sse-mesh-edge-item">
                                <div class="sse-mesh-edge-main">
                                    <span class="sse-mesh-edge-from"><?= $h($from['label'] ?? '?') ?></span>
                                    <span class="sse-mesh-edge-rel"><?= $h($e['relation_label'] ?? '') ?></span>
                                    <span class="sse-mesh-edge-to"><?= $h($to['label'] ?? '?') ?></span>
                                    <?php if (!empty($e['reliability_label'])): ?>
                                        <span class="badge badge--gray"><?= $h($e['reliability_label']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <form method="post" action="<?= $h(url('atak/sse/toiles/' . $meshId . '/liens/' . (int) $e['id'] . '/supprimer')) ?>" onsubmit="return confirm('Retirer ce lien de l’investigation ?');">
                                    <?= \App\Core\Csrf::field() ?>
                                    <button class="btn btn--ghost btn--sm" type="submit">Retirer</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
                <?php endif; ?>
            </div>

            <div class="sse-mesh-tabpanel" data-mesh-panel="settings" role="tabpanel" hidden>
                <section class="sse-mesh-block">
                    <h3 class="sse-mesh-block-title">Propriétés de l’investigation</h3>
                    <form method="post" action="<?= $h(url('atak/sse/toiles/' . $meshId)) ?>" class="sse-mesh-form">
                        <?= \App\Core\Csrf::field() ?>
                        <label for="mesh_title">Intitulé</label>
                        <input id="mesh_title" name="title" type="text" required value="<?= $h($mesh['title'] ?? '') ?>">
                        <div class="sse-mesh-form-row">
                            <div>
                                <label for="mesh_status">État</label>
                                <select id="mesh_status" name="status">
                                    <?php foreach ($statuses as $k => $lab): ?>
                                        <option value="<?= $h($k) ?>" <?= $statusKey === $k ? 'selected' : '' ?>><?= $h($lab) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="mesh_class">Diffusion</label>
                                <select id="mesh_class" name="classification">
                                    <?php foreach ($classifications as $k => $lab): ?>
                                        <option value="<?= $h($k) ?>" <?= $classKey === $k ? 'selected' : '' ?>><?= $h($lab) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <label for="mesh_summary">Objet</label>
                        <textarea id="mesh_summary" name="summary" rows="4" placeholder="Ce que cette investigation cherche à établir…"><?= $h($mesh['summary'] ?? '') ?></textarea>
                        <button class="btn" type="submit">Enregistrer</button>
                    </form>
                </section>
            </div>
            <?php else: ?>
            <div class="sse-mesh-tabpanel" data-mesh-panel="links" role="tabpanel" hidden>
                <section class="sse-mesh-block">
                    <h3 class="sse-mesh-block-title">Liens enregistrés</h3>
                    <?php if ($edges === []): ?>
                        <p class="muted">Aucun lien pour le moment.</p>
                    <?php else: ?>
                        <ul class="sse-mesh-edge-list">
                            <?php foreach ($edges as $e):
                                $from = $byId[(int) ($e['from_node_id'] ?? 0)] ?? null;
                                $to = $byId[(int) ($e['to_node_id'] ?? 0)] ?? null;
                                ?>
                                <li class="sse-mesh-edge-item">
                                    <div class="sse-mesh-edge-main">
                                        <span class="sse-mesh-edge-from"><?= $h($from['label'] ?? '?') ?></span>
                                        <span class="sse-mesh-edge-rel"><?= $h($e['relation_label'] ?? '') ?></span>
                                        <span class="sse-mesh-edge-to"><?= $h($to['label'] ?? '?') ?></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
            </div>
            <?php endif; ?>
        </div>
    </aside>
</div>

<script>
window.SSE_MESH = {
  meshId: <?= (int) $meshId ?>,
  canManage: <?= $canManage ? 'true' : 'false' ?>,
  csrf: <?= $j($csrf) ?>,
  mediaBase: <?= $j(rtrim(url(''), '/')) ?>,
  basePath: <?= $j(rtrim((string) (env('APP_BASE_PATH', '') ?: '/public'), '/') ?: '/public') ?>,
  layoutUrl: <?= $j(url('atak/sse/toiles/' . $meshId . '/disposition')) ?>,
  deleteNodeUrlTpl: <?= $j(url('atak/sse/toiles/' . $meshId . '/entites/__ID__/supprimer')) ?>,
  nodes: <?= $j($nodes) ?>,
  edges: <?= $j($edges) ?>,
  kindLabels: <?= $j($kindLabels) ?>
};
</script>
<script src="<?= $h(asset_url('assets/js/sse-mesh.js')) ?>?v=202608162330"></script>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
