<?php
declare(strict_types=1);
/** @var array<string,mixed> $graph */
/** @var callable $h */
$nodes = is_array($graph['nodes'] ?? null) ? $graph['nodes'] : [];
$edges = is_array($graph['edges'] ?? null) ? $graph['edges'] : [];
?>
<header class="iw-intel-col-head">
    <h2>Graphe d’exploitation</h2>
    <div class="iw-graph-tools">
        <label>Profondeur
            <select data-iw-graph-depth>
                <option value="1">1</option>
                <option value="2" selected>2</option>
                <option value="3">3</option>
            </select>
        </label>
        <label>Statut
            <select data-iw-graph-status>
                <option value="any">Tous</option>
                <option value="confirmed">Confirmées</option>
                <option value="proposed">Proposées</option>
            </select>
        </label>
        <button type="button" class="iw-btn iw-btn--tiny" data-iw-graph-reset>Recentrer</button>
    </div>
</header>
<div class="iw-graph-wrap">
    <svg id="sse-iw-graph" class="iw-graph-canvas" role="img" aria-label="Graphe des relations"></svg>
</div>
<p class="iw-intel-empty">
    <?= count($nodes) ?> entités · <?= count($edges) ?> liens —
    molette pour zoomer, glisser pour déplacer, clic pour sélectionner.
</p>
