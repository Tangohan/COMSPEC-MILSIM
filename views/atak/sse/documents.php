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
?>
<div class="page-heading">
    <div>
        <div class="page-heading-overline">Exploitation // Rédaction</div>
        <h1>Atelier de rédaction</h1>
        <p>
            Flash, comptes rendus, notes d’analyse et synthèses — documents rédigés,
            relus et validés avant toute diffusion. Rien ne sort du compartiment
            sans passage par cet atelier.
        </p>
    </div>
    <?php if ($canManage): ?>
        <div class="page-reference">
            <a class="btn" href="<?= $h(url('atak/sse/documents/nouveau')) ?>">Nouveau document</a>
        </div>
    <?php endif; ?>
</div>

<div class="sse-ops-grid">
    <a href="<?= $h(url('atak/sse/documents/nouveau?type=flash')) ?>">
        <strong>Flash renseignement</strong>
        <span>Alerte courte pour le poste de commandement</span>
    </a>
    <a href="<?= $h(url('atak/sse/documents/nouveau?type=compte_rendu')) ?>">
        <strong>Compte rendu</strong>
        <span>Situation · faits · personnel · matériel</span>
    </a>
    <a href="<?= $h(url('atak/sse/documents/nouveau?type=note_analyse')) ?>">
        <strong>Note d’analyse</strong>
        <span>Hypothèses, croisements, incertitudes</span>
    </a>
    <a href="<?= $h(url('atak/sse/documents/nouveau?type=synthese')) ?>">
        <strong>Synthèse de situation</strong>
        <span>Vue consolidée pour un cercle élargi</span>
    </a>
</div>

<section class="panel" style="margin-top:14px">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">19.10</span> Documents en atelier</div>
        <div class="panel-meta"><?= count($documents) ?></div>
    </div>
    <div class="panel-body">
        <form method="get" action="<?= $h(url('atak/sse/documents')) ?>" class="sse-filter-row">
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
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Référence</th>
                    <th>Intitulé</th>
                    <th>Type</th>
                    <th>Classification</th>
                    <th>État</th>
                    <th>Dossier</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($documents as $doc): ?>
                    <tr>
                        <td class="record-id"><?= $h($doc['reference_code'] ?? '') ?></td>
                        <td><?= $h($doc['title'] ?? '') ?></td>
                        <td><?= $h($doc['document_type_label'] ?? '') ?></td>
                        <td><span class="badge"><?= $h($doc['classification_label'] ?? '') ?></span></td>
                        <td><span class="badge"><?= $h($doc['status_label'] ?? '') ?></span></td>
                        <td>
                            <?php if (!empty($doc['case_id'])): ?>
                                <a class="link" href="<?= $h(url('atak/sse/dossiers/' . (int) $doc['case_id'])) ?>">
                                    <?= $h($doc['case_reference'] ?: 'Dossier') ?>
                                </a>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a class="btn-open" href="<?= $h(url('atak/sse/documents/' . (int) ($doc['id'] ?? 0))) ?>">Ouvrir</a>
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
