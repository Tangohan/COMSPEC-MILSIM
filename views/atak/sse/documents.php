<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $documents */
$documents = is_array($documents ?? null) ? $documents : [];
/** @var array<string,string> $typeLabels */
$typeLabels = is_array($typeLabels ?? null) ? $typeLabels : [];
/** @var array<string,string> $statusLabels */
$statusLabels = is_array($statusLabels ?? null) ? $statusLabels : [];
$filterStatus = (string) ($filterStatus ?? '');
$filterType = (string) ($filterType ?? '');
$filterQ = (string) ($filterQ ?? '');
$canManage = (bool) ($canManage ?? false);

$typeMeta = [
    'flash' => ['code' => 'FLH', 'hint' => 'Alerte courte pour le poste de commandement'],
    'compte_rendu' => ['code' => 'CR', 'hint' => 'Situation · faits · personnel · matériel'],
    'note_analyse' => ['code' => 'NA', 'hint' => 'Hypothèses, croisements, incertitudes'],
    'synthese' => ['code' => 'SYN', 'hint' => 'Vue consolidée pour un cercle élargi'],
];

$statusClass = static function (string $status): string {
    return match ($status) {
        'brouillon' => 'badge--gray',
        'en_relecture' => 'badge--amber',
        'valide' => '',
        'archive' => 'badge--gray',
        default => 'badge--gray',
    };
};

$draftCount = 0;
$reviewCount = 0;
$validCount = 0;
foreach ($documents as $d) {
    $st = (string) ($d['status'] ?? '');
    if ($st === 'brouillon') {
        $draftCount++;
    } elseif ($st === 'en_relecture') {
        $reviewCount++;
    } elseif ($st === 'valide') {
        $validCount++;
    }
}
?>
<section class="sse-desk-hero" aria-labelledby="sse-desk-title">
    <div class="sse-desk-hero__main">
        <div class="sse-desk-hero__kicker">
            <span class="interest-hero__ref">ATH-SSE-REDAC</span>
            <span class="badge badge--red">Diffusion restreinte</span>
        </div>
        <h1 id="sse-desk-title">Atelier de rédaction</h1>
        <p class="sse-desk-hero__lead">
            Flash, comptes rendus, notes d’analyse et synthèses — rédigés, relus et validés
            avant toute diffusion. Rien ne sort du compartiment sans passage par cet atelier.
        </p>
        <dl class="interest-facts interest-facts--dense">
            <div>
                <dt>Brouillons</dt>
                <dd><?= $draftCount ?></dd>
            </div>
            <div>
                <dt>En relecture</dt>
                <dd><?= $reviewCount ?></dd>
            </div>
            <div>
                <dt>Validés</dt>
                <dd><?= $validCount ?></dd>
            </div>
            <div>
                <dt>Visibles</dt>
                <dd><?= count($documents) ?></dd>
            </div>
        </dl>
    </div>
    <aside class="sse-desk-hero__side">
        <p class="interest-hero__side-label">Production</p>
        <?php if ($canManage): ?>
            <div class="interest-hero__actions">
                <a class="btn" href="<?= $h(url('atak/sse/documents/nouveau')) ?>">Nouveau document</a>
                <a class="btn btn--ghost" href="<?= $h(url('atak/sse/rapports')) ?>">Voir les rapports</a>
            </div>
        <?php else: ?>
            <p class="muted" style="margin:0;font-size:.82rem;line-height:1.4">
                Lecture seule sur cet atelier. Demandez une habilitation de rédaction si vous devez produire.
            </p>
        <?php endif; ?>
        <div class="interest-hero__source">
            <strong>Circuit</strong>
            <span>Brouillon → relecture → validation → diffusion</span>
        </div>
    </aside>
</section>

<div class="security-notice">
    <div class="security-notice-code">SEC-DOC</div>
    <div>
        <strong>Produits classifiés</strong>
        <span>
            Chaque document porte une classification. Une diffusion plus large passe
            par une version caviardée et une validation explicite — jamais par copie libre.
        </span>
    </div>
</div>

<?php if ($canManage): ?>
<nav class="sse-desk-types" aria-label="Types de document">
    <?php foreach ($typeMeta as $typeKey => $meta): ?>
        <?php if (!isset($typeLabels[$typeKey])) { continue; } ?>
        <a class="sse-desk-type" href="<?= $h(url('atak/sse/documents/nouveau?type=' . rawurlencode($typeKey))) ?>">
            <span class="sse-desk-type__code"><?= $h($meta['code']) ?></span>
            <strong><?= $h($typeLabels[$typeKey]) ?></strong>
            <span><?= $h($meta['hint']) ?></span>
        </a>
    <?php endforeach; ?>
</nav>
<?php endif; ?>

<section class="panel sse-desk-panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">19.10</span> Documents en atelier</div>
        <div class="panel-meta"><?= count($documents) ?> pièce<?= count($documents) > 1 ? 's' : '' ?></div>
    </div>
    <div class="panel-body">
        <form method="get" action="<?= $h(url('atak/sse/documents')) ?>" class="sse-filter-row sse-desk-filters">
            <label class="sr-only" for="doc-q">Recherche</label>
            <input id="doc-q" name="q" type="search" value="<?= $h($filterQ) ?>" placeholder="Référence ou intitulé…">
            <label class="sr-only" for="doc-status">État</label>
            <select id="doc-status" name="status">
                <option value="">Tous les états</option>
                <?php foreach ($statusLabels as $k => $lab): ?>
                    <option value="<?= $h($k) ?>" <?= $filterStatus === $k ? 'selected' : '' ?>><?= $h($lab) ?></option>
                <?php endforeach; ?>
            </select>
            <label class="sr-only" for="doc-type">Type</label>
            <select id="doc-type" name="type">
                <option value="">Tous les types</option>
                <?php foreach ($typeLabels as $k => $lab): ?>
                    <option value="<?= $h($k) ?>" <?= $filterType === $k ? 'selected' : '' ?>><?= $h($lab) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn--ghost" type="submit">Filtrer</button>
        </form>
    </div>
    <?php if ($documents === []): ?>
        <div class="empty-state">
            <div class="empty-state-inner">
                <div class="empty-symbol">DOC</div>
                <strong>Aucun document pour l’instant</strong>
                <p>Ouvrez un brouillon depuis un dossier, ou créez un document libre dans l’atelier.</p>
                <?php if ($canManage): ?>
                    <a class="btn btn--ghost" href="<?= $h(url('atak/sse/documents/nouveau')) ?>">Rédiger un document</a>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="sse-desk-list">
            <?php foreach ($documents as $doc): ?>
                <?php
                $st = (string) ($doc['status'] ?? '');
                $dtype = (string) ($doc['document_type'] ?? '');
                $code = $typeMeta[$dtype]['code'] ?? 'DOC';
                ?>
                <article class="sse-desk-card">
                    <div class="sse-desk-card__mark" aria-hidden="true"><?= $h($code) ?></div>
                    <div class="sse-desk-card__body">
                        <header class="sse-desk-card__head">
                            <span class="record-id"><?= $h($doc['reference_code'] ?? '') ?></span>
                            <span class="badge <?= $h($statusClass($st)) ?>"><?= $h($doc['status_label'] ?? '') ?></span>
                            <span class="badge"><?= $h($doc['classification_label'] ?? '') ?></span>
                        </header>
                        <h2 class="sse-desk-card__title">
                            <a class="link" href="<?= $h(url('atak/sse/documents/' . (int) ($doc['id'] ?? 0))) ?>">
                                <?= $h($doc['title'] ?? 'Sans titre') ?>
                            </a>
                        </h2>
                        <p class="sse-desk-card__meta">
                            <?= $h($doc['document_type_label'] ?? 'Document') ?>
                            <?php if (!empty($doc['case_id'])): ?>
                                ·
                                <a class="link" href="<?= $h(url('atak/sse/dossiers/' . (int) $doc['case_id'])) ?>">
                                    <?= $h($doc['case_reference'] ?: 'Dossier lié') ?>
                                </a>
                            <?php else: ?>
                                · <span class="muted">Sans dossier</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="sse-desk-card__action">
                        <a class="btn-open" href="<?= $h(url('atak/sse/documents/' . (int) ($doc['id'] ?? 0))) ?>">Ouvrir</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
