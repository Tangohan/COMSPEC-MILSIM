<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $meshes */
/** @var array<int,array{nodes:int,edges:int}> $meshCounts */
/** @var array{status:string,q:string} $filters */
/** @var array<string,string> $statuses */
/** @var bool $canManage */
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
            Graphes d’enquête centrés sur les objets : identités, sites, matériels et liens.
            Une investigation organise les hypothèses — elle ne constitue pas une preuve à elle seule.
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

<form class="toolbar" method="get" action="<?= $h(url('atak/sse/toiles')) ?>">
    <div class="toolbar-field">
        <label for="q">Recherche</label>
        <input id="q" name="q" type="search" value="<?= $h($filters['q'] ?? '') ?>" placeholder="Réf. ou intitulé…">
    </div>
    <div class="toolbar-field">
        <label for="status">État</label>
        <select id="status" name="status">
            <option value="">Tous</option>
            <?php foreach ($statuses as $k => $lab): ?>
                <option value="<?= $h($k) ?>" <?= ($filters['status'] ?? '') === $k ? 'selected' : '' ?>><?= $h($lab) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="toolbar-actions">
        <button class="btn btn--ghost" type="submit">Filtrer</button>
    </div>
</form>

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
                ?>
                <a class="sse-folder-card sse-mesh-card" href="<?= $h(url('atak/sse/toiles/' . (int) $m['id'])) ?>">
                    <div class="sse-folder-card-top">
                        <span class="sse-folder-kind">Toile</span>
                        <span class="badge"><?= $h($m['status_label'] ?? '') ?></span>
                    </div>
                    <strong><?= $h($m['title'] ?? '') ?></strong>
                    <span class="record-id"><?= $h($m['reference_code'] ?? '') ?></span>
                    <div class="sse-folder-card-meta">
                        <span class="badge badge--amber"><?= $h($m['classification_label'] ?? '') ?></span>
                        <span class="sse-count-set">
                            <span class="sse-count"><span class="sse-count-n"><?= (int) $cnt['nodes'] ?></span> entités</span>
                            <span class="sse-count"><span class="sse-count-n"><?= (int) $cnt['edges'] ?></span> liens</span>
                        </span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
