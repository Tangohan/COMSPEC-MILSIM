<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $recentCases */
$recentCases = is_array($recentCases ?? null) ? $recentCases : [];
/** @var list<array<string,mixed>> $recentDocuments */
$recentDocuments = is_array($recentDocuments ?? null) ? $recentDocuments : [];
$draftDocs = array_values(array_filter(
    $recentDocuments,
    static fn (array $d): bool => in_array((string) ($d['status'] ?? ''), ['brouillon', 'en_relecture'], true)
));
$draftDocs = array_slice($draftDocs, 0, 8);
?>
<div class="page-heading">
    <div>
        <div class="page-heading-overline">Exploitation // Rapports</div>
        <h1>Rapports</h1>
        <p>
            Comptes rendus, versions expurgées et exports PDF — produits depuis les dossiers
            et investigations, jamais diffusés sans relecture dans l’atelier de rédaction.
        </p>
    </div>
    <div class="page-reference">
        <a class="btn" href="<?= $h(url('atak/sse/documents/nouveau')) ?>">Nouveau document</a>
    </div>
</div>

<div class="sse-ops-grid">
    <a href="<?= $h(url('atak/sse/documents')) ?>">
        <strong>Atelier de rédaction</strong>
        <span>Flash, comptes rendus, notes et synthèses</span>
    </a>
    <a href="<?= $h(url('atak/sse/dossiers')) ?>">
        <strong>Dossiers validés</strong>
        <span>Ouvrir un dossier pour produire un compte rendu</span>
    </a>
    <a href="<?= $h(url('atak/sse/toiles')) ?>">
        <strong>Investigations</strong>
        <span>Synthèse d’une toile relationnelle</span>
    </a>
    <a href="<?= $h(url('atak/sse/exploitation-numerique/rapports')) ?>">
        <strong>Exploitation numérique</strong>
        <span>Rapports liés aux supports et acquisitions</span>
    </a>
</div>

<section class="panel" style="margin-top:14px">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">19.00</span> Brouillons en cours</div>
        <div class="panel-meta"><?= count($draftDocs) ?></div>
    </div>
    <?php if ($draftDocs === []): ?>
        <div class="empty-state">
            <div class="empty-state-inner">
                <div class="empty-symbol">BRU</div>
                <strong>Aucun brouillon ouvert</strong>
                <p>Créez un document ou ouvrez un brouillon depuis le compte rendu d’un dossier.</p>
                <a class="btn btn--ghost" href="<?= $h(url('atak/sse/documents/nouveau')) ?>">Rédiger</a>
            </div>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr><th>Référence</th><th>Intitulé</th><th>Type</th><th>État</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($draftDocs as $doc): ?>
                    <tr>
                        <td class="record-id"><?= $h($doc['reference_code'] ?? '') ?></td>
                        <td><?= $h($doc['title'] ?? '') ?></td>
                        <td><?= $h($doc['document_type_label'] ?? '') ?></td>
                        <td><span class="badge"><?= $h($doc['status_label'] ?? '') ?></span></td>
                        <td><a class="btn-open" href="<?= $h(url('atak/sse/documents/' . (int) ($doc['id'] ?? 0))) ?>">Ouvrir</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="panel" style="margin-top:14px">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">19.01</span> Dossiers prêts pour compte rendu</div>
        <div class="panel-meta"><?= count($recentCases) ?></div>
    </div>
    <?php if ($recentCases === []): ?>
        <div class="empty-state">
            <div class="empty-state-inner">
                <div class="empty-symbol">RPT</div>
                <strong>Aucun dossier listé</strong>
                <p>Créez ou clôturez un dossier pour pouvoir en produire un compte rendu.</p>
                <a class="btn btn--ghost" href="<?= $h(url('atak/sse/dossiers')) ?>">Voir les dossiers</a>
            </div>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr><th>Référence</th><th>Intitulé</th><th>État</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($recentCases as $c): ?>
                    <tr>
                        <td class="record-id"><?= $h($c['reference_code'] ?? '') ?></td>
                        <td><?= $h($c['title'] ?? '') ?></td>
                        <td><span class="badge"><?= $h($c['status_label'] ?? '') ?></span></td>
                        <td>
                            <a class="btn-open" href="<?= $h(url('atak/sse/dossiers/' . (int) ($c['id'] ?? 0) . '/compte-rendu')) ?>">Compte rendu</a>
                            <a class="btn-open" href="<?= $h(url('atak/sse/dossiers/' . (int) ($c['id'] ?? 0) . '/pdf')) ?>">PDF complet</a>
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
