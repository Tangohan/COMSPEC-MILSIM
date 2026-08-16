<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $meshes */
/** @var array<int,array{nodes:int,edges:int}> $meshCounts */
/** @var array<int,string> $caseLabels */
/** @var array{total:int,open:int,analysis:int,entities:int,links:int} $metrics */
/** @var list<array<string,mixed>> $mergeCandidates */
/** @var array{status:string,q:string} $filters */
/** @var array<string,string> $statuses */
/** @var bool $canManage */
$caseLabels = is_array($caseLabels ?? null) ? $caseLabels : [];
$metrics = is_array($metrics ?? null) ? $metrics : [
    'total' => count($meshes),
    'open' => 0,
    'analysis' => 0,
    'entities' => 0,
    'links' => 0,
];
$mergeCandidates = is_array($mergeCandidates ?? null) ? $mergeCandidates : [];
$searchQuery = trim((string) ($filters['q'] ?? ''));
$statusFilter = (string) ($filters['status'] ?? '');
?>
<div class="breadcrumb">
    Athena / SSE / Analyse /
    <strong>Investigations</strong>
</div>

<div class="page-heading">
    <div>
        <div class="page-heading-overline">Analyse // Graphe relationnel</div>
        <h1>Investigations</h1>
        <p>
            Chaque investigation est une toile d’hypothèses : identités, sites, matériels et liens.
            Vous pouvez en ouvrir plusieurs, puis les regrouper en une seule quand les pistes convergent.
        </p>
    </div>
    <div class="page-reference">
        <strong>Vue // Investigations</strong>
        Réf. ATH-SSE-MESH
        <?php if ($canManage): ?>
            <div style="margin-top:.5rem">
                <a class="btn" href="<?= $h(url('atak/sse/toiles/nouveau')) ?>">Ouvrir une investigation</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<form class="sse-toolbar-search" method="get" action="<?= $h(url('atak/sse/toiles')) ?>" role="search">
    <label for="mesh-q">Rechercher</label>
    <div class="case-search-control">
        <input
            id="mesh-q"
            name="q"
            type="search"
            value="<?= $h($searchQuery) ?>"
            placeholder="Référence, intitulé, objet de l’investigation…"
        >
        <button type="submit" aria-label="Lancer la recherche">→</button>
    </div>
    <div class="toolbar-field" style="margin:0">
        <label for="mesh-status" class="sr-only">État</label>
        <select id="mesh-status" name="status" onchange="this.form.submit()">
            <option value="">Tous les états</option>
            <?php foreach ($statuses as $k => $lab): ?>
                <option value="<?= $h($k) ?>" <?= $statusFilter === $k ? 'selected' : '' ?>><?= $h($lab) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php if ($searchQuery !== '' || $statusFilter !== ''): ?>
        <a class="link" href="<?= $h(url('atak/sse/toiles')) ?>">Effacer</a>
    <?php endif; ?>
</form>

<div class="metrics-grid">
    <div class="metric">
        <div class="metric-label">Investigations</div>
        <div class="metric-value"><?= $h(str_pad((string) (int) ($metrics['total'] ?? 0), 3, '0', STR_PAD_LEFT)) ?></div>
        <div class="metric-detail">Sur cette vue</div>
    </div>
    <div class="metric">
        <div class="metric-label">Ouvertes</div>
        <div class="metric-value"><?= $h(str_pad((string) (int) ($metrics['open'] ?? 0), 3, '0', STR_PAD_LEFT)) ?></div>
        <div class="metric-detail">À démarrer</div>
    </div>
    <div class="metric">
        <div class="metric-label">En analyse</div>
        <div class="metric-value"><?= $h(str_pad((string) (int) ($metrics['analysis'] ?? 0), 3, '0', STR_PAD_LEFT)) ?></div>
        <div class="metric-detail">Travaux en cours</div>
    </div>
    <div class="metric">
        <div class="metric-label">Graphe</div>
        <div class="metric-value"><?= $h((int) ($metrics['entities'] ?? 0)) ?></div>
        <div class="metric-detail"><?= (int) ($metrics['links'] ?? 0) ?> lien<?= ((int) ($metrics['links'] ?? 0)) > 1 ? 's' : '' ?></div>
    </div>
</div>

<?php if ($canManage && count($meshes) >= 2): ?>
<section class="panel sse-mesh-merge-panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">07.00</span> Regrouper des investigations</div>
        <div class="panel-meta">Fusionner plusieurs toiles en une</div>
    </div>
    <div class="panel-body">
        <p class="muted" style="margin-top:0">
            Cochez les investigations à rassembler, puis choisissez de les intégrer dans une toile
            existante ou d’en créer une nouvelle. Les entités déjà liées au même dossier / fiche
            sont réutilisées ; les toiles sources sont archivées.
        </p>
        <form id="sse-mesh-merge" method="post" action="<?= $h(url('atak/sse/toiles/regrouper')) ?>" class="sse-mesh-merge-form">
            <?= \App\Core\Csrf::field() ?>
            <div class="grid-2">
                <div>
                    <label for="merge_mode">Mode de regroupement</label>
                    <select id="merge_mode" name="merge_mode">
                        <option value="existing">Intégrer dans une investigation existante</option>
                        <option value="new">Créer une nouvelle investigation regroupée</option>
                    </select>
                </div>
                <div data-merge-existing>
                    <label for="target_id">Investigation qui conserve le graphe</label>
                    <select id="target_id" name="target_id">
                        <option value="">Choisir…</option>
                        <?php foreach ($mergeCandidates as $m): ?>
                            <option value="<?= (int) $m['id'] ?>">
                                <?= $h(($m['reference_code'] ?? '') . ' — ' . ($m['title'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div data-merge-new hidden>
                    <label for="new_title">Intitulé de la nouvelle investigation</label>
                    <input id="new_title" name="new_title" type="text" maxlength="200"
                           placeholder="Ex. Synthèse réseau Alpha + Bravo">
                </div>
            </div>
            <div class="sse-mesh-merge-actions">
                <button class="btn" type="submit">Regrouper la sélection</button>
                <span class="muted" id="sse-mesh-merge-count">Aucune investigation cochée</span>
            </div>
        </form>
    </div>
</section>
<?php endif; ?>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">07.01</span> Registre des investigations</div>
        <div class="panel-meta"><?= count($meshes) ?> investigation<?= count($meshes) > 1 ? 's' : '' ?></div>
    </div>
    <?php if ($meshes === []): ?>
        <div class="empty-state">
            <div class="empty-state-inner">
                <div class="empty-symbol">INV</div>
                <strong>Aucune investigation</strong>
                <p>Ouvrez une investigation vide ou importez le graphe d’un dossier d’affaire.</p>
                <?php if ($canManage): ?>
                    <a class="btn" href="<?= $h(url('atak/sse/toiles/nouveau')) ?>">Ouvrir une investigation</a>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="sse-folder-grid">
            <?php foreach ($meshes as $m):
                $cnt = $meshCounts[(int) $m['id']] ?? ['nodes' => 0, 'edges' => 0];
                $meshUrl = url('atak/sse/toiles/' . (int) $m['id']);
                $meshRef = (string) ($m['reference_code'] ?? '');
                $meshTitle = (string) ($m['title'] ?? '');
                $summary = trim((string) ($m['summary'] ?? ''));
                $caseId = (int) ($m['case_id'] ?? 0);
                $caseLabel = $caseId > 0 ? (string) ($caseLabels[$caseId] ?? '') : '';
                $meshCtx = [
                    ['label' => 'Ouvrir l’investigation', 'href' => $meshUrl],
                    ['label' => 'Ouvrir dans un nouvel onglet', 'href' => $meshUrl, 'target' => '_blank'],
                    ['separator' => true],
                    ['label' => 'Copier la référence', 'copy' => $meshRef],
                ];
                if ($caseId > 0) {
                    $meshCtx[] = ['label' => 'Ouvrir le dossier lié', 'href' => url('atak/sse/dossiers/' . $caseId)];
                }
                $nodeCount = (int) $cnt['nodes'];
                $edgeCount = (int) $cnt['edges'];
                ?>
                <article class="sse-folder-card sse-mesh-card"
                         data-sse-ctx-title="<?= $h($meshTitle) ?>"
                         data-sse-ctx-actions="<?= $h(json_encode($meshCtx, JSON_UNESCAPED_UNICODE)) ?>">
                    <?php if ($canManage && count($meshes) >= 2): ?>
                        <label class="sse-mesh-pick" title="Sélectionner pour regroupement">
                            <input type="checkbox" form="sse-mesh-merge" name="mesh_ids[]"
                                   value="<?= (int) $m['id'] ?>" data-mesh-pick>
                            <span class="sr-only">Sélectionner <?= $h($meshTitle) ?></span>
                        </label>
                    <?php endif; ?>
                    <a class="sse-mesh-card-link" href="<?= $h($meshUrl) ?>">
                        <div class="sse-folder-card-top">
                            <span class="sse-folder-kind">Investigation</span>
                            <span class="badge"><?= $h($m['status_label'] ?? '') ?></span>
                        </div>
                        <strong><?= $h($meshTitle) ?></strong>
                        <span class="record-id"><?= $h($meshRef) ?></span>
                        <?php if ($summary !== ''): ?>
                            <p class="sse-mesh-card-excerpt"><?= $h(mb_substr($summary, 0, 160)) ?><?= mb_strlen($summary) > 160 ? '…' : '' ?></p>
                        <?php endif; ?>
                        <div class="sse-folder-card-meta">
                            <span class="badge badge--amber"><?= $h($m['classification_label'] ?? '') ?></span>
                            <span class="sse-count-set">
                                <span class="sse-count"><span class="sse-count-n"><?= $nodeCount ?></span> entité<?= $nodeCount > 1 ? 's' : '' ?></span>
                                <span class="sse-count"><span class="sse-count-n"><?= $edgeCount ?></span> lien<?= $edgeCount > 1 ? 's' : '' ?></span>
                            </span>
                        </div>
                        <?php if ($caseLabel !== ''): ?>
                            <span class="sse-mesh-card-case">Dossier : <?= $h($caseLabel) ?></span>
                        <?php endif; ?>
                    </a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php if ($canManage && count($meshes) >= 2): ?>
<script>
(() => {
    const mode = document.getElementById('merge_mode');
    const existing = document.querySelector('[data-merge-existing]');
    const neu = document.querySelector('[data-merge-new]');
    const countEl = document.getElementById('sse-mesh-merge-count');
    const picks = () => Array.from(document.querySelectorAll('[data-mesh-pick]:checked'));
    const syncMode = () => {
        const isNew = mode && mode.value === 'new';
        if (existing) existing.hidden = !!isNew;
        if (neu) neu.hidden = !isNew;
    };
    const syncCount = () => {
        const n = picks().length;
        if (!countEl) return;
        countEl.textContent = n === 0
            ? 'Aucune investigation cochée'
            : (n === 1 ? '1 investigation cochée' : n + ' investigations cochées');
    };
    mode?.addEventListener('change', syncMode);
    document.querySelectorAll('[data-mesh-pick]').forEach((el) => el.addEventListener('change', syncCount));
    syncMode();
    syncCount();
})();
</script>
<?php endif; ?>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
