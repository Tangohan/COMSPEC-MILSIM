<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array<string,mixed>|null $document */
$document = is_array($document ?? null) ? $document : null;
$isEdit = $document !== null;
/** @var array<string,string> $typeLabels */
$typeLabels = is_array($typeLabels ?? null) ? $typeLabels : [];
/** @var array<string,string> $statusLabels */
$statusLabels = is_array($statusLabels ?? null) ? $statusLabels : [];
/** @var array<string,string> $classifications */
$classifications = is_array($classifications ?? null) ? $classifications : [];
/** @var list<array<string,mixed>> $cases */
$cases = is_array($cases ?? null) ? $cases : [];
$prefillType = (string) ($prefillType ?? ($document['document_type'] ?? 'note_analyse'));
$prefillCaseId = (int) ($prefillCaseId ?? ($document['case_id'] ?? 0));
$prefillTitle = (string) ($prefillTitle ?? ($document['title'] ?? ''));
$prefillBody = (string) ($prefillBody ?? ($document['body'] ?? ''));
$prefillClass = (string) ($prefillClass ?? ($document['classification'] ?? 'confidentiel'));
$prefillStatus = (string) ($prefillStatus ?? ($document['status'] ?? 'brouillon'));
$action = $isEdit
    ? url('atak/sse/documents/' . (int) $document['id'])
    : url('atak/sse/documents');
$typeHints = [
    'flash' => 'Gardez-le court : qui, quoi, où, quand, et la recommandation immédiate.',
    'compte_rendu' => 'Structurez situation → faits → personnel → matériel → suite à donner.',
    'note_analyse' => 'Séparez faits établis, hypothèses et incertitudes. Indiquez le niveau de confiance.',
    'synthese' => 'Vue d’ensemble pour un cercle élargi : pas de détail opérationnel inutile.',
    'diffusion' => 'Version destinée à une diffusion contrôlée — déjà caviardée si besoin.',
];
?>
<div class="breadcrumb">
    Athena / SSE /
    <a class="link" href="<?= $h(url('atak/sse/documents')) ?>">Rédaction</a> /
    <strong><?= $isEdit ? 'Modifier' : 'Nouveau' ?></strong>
</div>

<section class="sse-desk-hero sse-desk-hero--compact" aria-labelledby="sse-doc-form-title">
    <div class="sse-desk-hero__main">
        <div class="sse-desk-hero__kicker">
            <span class="interest-hero__ref"><?= $isEdit ? $h($document['reference_code'] ?? 'DOC') : 'ATH-SSE-BROUILLON' ?></span>
            <?php if ($isEdit): ?>
                <span class="badge"><?= $h($document['status_label'] ?? '') ?></span>
            <?php else: ?>
                <span class="badge badge--gray">Nouveau brouillon</span>
            <?php endif; ?>
        </div>
        <h1 id="sse-doc-form-title"><?= $isEdit ? 'Modifier le document' : 'Nouveau document' ?></h1>
        <p class="sse-desk-hero__lead">
            Rédigez un produit de renseignement structuré. Conservez la classification
            au niveau réel du contenu — la diffusion plus large se fait ensuite via
            caviardage et validation.
        </p>
    </div>
</section>

<section class="panel sse-desk-panel sse-desk-editor">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">19.11</span>
            Fiche document
        </div>
        <div class="panel-meta"><?= $isEdit ? 'Mise à jour' : 'Création' ?></div>
    </div>
    <div class="panel-body">
        <form method="post" action="<?= $h($action) ?>" class="sse-doc-form">
            <?= \App\Core\Csrf::field() ?>

            <div class="sse-form-grid">
                <div>
                    <label for="document_type">Type de document</label>
                    <select id="document_type" name="document_type" required>
                        <?php foreach ($typeLabels as $k => $lab): ?>
                            <option value="<?= $h($k) ?>" <?= $prefillType === $k ? 'selected' : '' ?>><?= $h($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="sse-desk-hint" id="sse-type-hint"><?= $h($typeHints[$prefillType] ?? 'Choisissez le format adapté au destinataire.') ?></p>
                </div>
                <div>
                    <label for="classification">Classification</label>
                    <select id="classification" name="classification" required>
                        <?php foreach ($classifications as $k => $lab): ?>
                            <option value="<?= $h($k) ?>" <?= $prefillClass === $k ? 'selected' : '' ?>><?= $h($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="status">État</label>
                    <select id="status" name="status">
                        <?php foreach ($statusLabels as $k => $lab): ?>
                            <?php if ($k === 'archive' && !$isEdit) { continue; } ?>
                            <option value="<?= $h($k) ?>" <?= $prefillStatus === $k ? 'selected' : '' ?>><?= $h($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="case_id">Dossier lié (optionnel)</label>
                    <select id="case_id" name="case_id">
                        <option value="">Aucun dossier</option>
                        <?php foreach ($cases as $c): ?>
                            <?php if (!empty($c['is_folder'])) { continue; } ?>
                            <option value="<?= (int) ($c['id'] ?? 0) ?>" <?= $prefillCaseId === (int) ($c['id'] ?? 0) ? 'selected' : '' ?>">
                                <?= $h(($c['reference_code'] ?? '') . ' — ' . ($c['title'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <label for="title">Intitulé</label>
            <input id="title" name="title" type="text" required maxlength="240"
                   value="<?= $h($prefillTitle) ?>"
                   placeholder="Ex. Flash — découverte cache armes secteur Nord">

            <label for="body">Corps du document</label>
            <div class="sse-desk-paper">
                <textarea id="body" name="body" rows="22" required
                          placeholder="Rédigez ici. Structurez par paragraphes clairs : situation, faits, analyse, recommandations."><?= $h($prefillBody) ?></textarea>
            </div>
            <p class="muted sse-desk-footnote">
                Évitez les noms en clair si le document doit ensuite être diffusé plus largement.
                Pour une version expurgée à partir d’un dossier, utilisez aussi l’écran de déclassification.
            </p>

            <div class="toolbar-actions sse-desk-actions">
                <button class="btn" type="submit"><?= $isEdit ? 'Enregistrer' : 'Créer le brouillon' ?></button>
                <a class="btn btn--ghost" href="<?= $h($isEdit ? url('atak/sse/documents/' . (int) $document['id']) : url('atak/sse/documents')) ?>">Annuler</a>
            </div>
        </form>
    </div>
</section>
<script>
(function () {
    var hints = <?= json_encode($typeHints, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var sel = document.getElementById('document_type');
    var out = document.getElementById('sse-type-hint');
    if (!sel || !out) { return; }
    sel.addEventListener('change', function () {
        out.textContent = hints[sel.value] || 'Choisissez le format adapté au destinataire.';
    });
})();
</script>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
