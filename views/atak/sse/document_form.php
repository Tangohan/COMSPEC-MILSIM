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
/** @var array<string,string> $bodyTemplates */
$bodyTemplates = is_array($bodyTemplates ?? null) ? $bodyTemplates : [];
/** @var list<array<string,mixed>> $libraryEntries */
$libraryEntries = is_array($libraryEntries ?? null) ? $libraryEntries : [];
/** @var list<array<string,mixed>> $contextualSuggestions */
$contextualSuggestions = is_array($contextualSuggestions ?? null) ? $contextualSuggestions : [];
/** @var array<string,string> $libraryCategories */
$libraryCategories = is_array($libraryCategories ?? null) ? $libraryCategories : [];
$libraryUsedCategories = [];
foreach ($libraryEntries as $entry) {
    $libraryUsedCategories[(string) ($entry['category'] ?? '')] = true;
}
$prefillType = (string) ($prefillType ?? ($document['document_type'] ?? 'note_analyse'));
$prefillCaseId = (int) ($prefillCaseId ?? ($document['case_id'] ?? 0));
$prefillTitle = (string) ($prefillTitle ?? ($document['title'] ?? ''));
$prefillBody = (string) ($prefillBody ?? ($document['body'] ?? ''));
$editorContext = is_array($editorContext ?? null) ? $editorContext : [];
$bodyHtml = \App\Support\SseRichText::htmlOf($prefillBody);
$prefillClass = (string) ($prefillClass ?? ($document['classification'] ?? 'confidentiel'));
$prefillStatus = (string) ($prefillStatus ?? ($document['status'] ?? 'brouillon'));
$authorPreview = (string) ($authorPreview ?? (\App\Core\Session::get('sse_guest_label') ?? \App\Core\Session::get('display_name') ?? 'Rédacteur'));
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

$docId = $isEdit ? (int) ($document['id'] ?? 0) : 0;
$edCfg = [
    'templates' => $editorContext['templates'] ?? [],
    'fields' => $editorContext['fields'] ?? [],
    'serverDraft' => $editorContext['serverDraft'] ?? null,
    'draftUrl' => (string) ($editorContext['draftUrl'] ?? ''),
];
$ico = static function (string $d): string {
    return '<svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false"><path d="' . $d . '" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>';
};
$paletteText = \App\Support\SseRichText::TEXT_COLORS;
$paletteMark = \App\Support\SseRichText::HIGHLIGHT_COLORS;
$btn = static function (string $cmd, string $label, string $inner, bool $toggle = false) use ($h): string {
    return '<button type="button" class="sse-ed__btn" data-cmd="' . $h($cmd) . '" title="' . $h($label) . '" aria-label="' . $h($label) . '"'
        . ($toggle ? ' aria-pressed="false"' : '') . '>' . $inner . '</button>';
};
?>

<link rel="stylesheet" href="<?= $h(asset_url('assets/css/sse-doc-editor.css')) ?>">

<section class="sse-desk-hero sse-desk-hero--compact" aria-labelledby="sse-doc-form-title">
    <div class="sse-desk-hero__main">
        <h1 id="sse-doc-form-title"><?= $isEdit ? 'Modifier le document' : 'Nouveau document' ?></h1>
        <p class="sse-desk-hero__lead">
            <span class="interest-hero__ref"><?= $isEdit ? $h($document['reference_code'] ?? 'DOC') : 'ATH-SSE-BROUILLON' ?></span>
            <?php if ($isEdit): ?>
                <span class="badge"><?= $h($document['status_label'] ?? '') ?></span>
            <?php else: ?>
                <span class="badge badge--gray">Nouveau brouillon</span>
            <?php endif; ?>
            Rédigez directement sur la feuille. Conservez la classification au niveau réel du contenu : la diffusion plus large
            se fait ensuite via caviardage et validation.
        </p>
    </div>
</section>

<form method="post" action="<?= $h($action) ?>" class="sse-ed-form" id="sse-doc-form">
    <?= \App\Core\Csrf::field() ?>

    <section class="panel sse-desk-panel sse-ed-meta" aria-label="Fiche du document">
        <div class="panel-body">
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
                            <option value="<?= (int) ($c['id'] ?? 0) ?>" <?= $prefillCaseId === (int) ($c['id'] ?? 0) ? 'selected' : '' ?>
                                data-ref="<?= $h($c['reference_code'] ?? '') ?>">
                                <?= $h(($c['reference_code'] ?? '') . ' — ' . ($c['title'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($isEdit && $docId > 0): ?>
                        <?= \App\Services\Sse\Attach\SseAttachWindow::button('document', (int) $document['id'], ['label' => 'Rattacher à une piste, une personne…', 'class' => 'btn btn--ghost btn--sm']) ?>
                    <?php endif; ?>
                </div>
            </div>
            <label for="title">Intitulé</label>
            <input id="title" name="title" type="text" required maxlength="240"
                   value="<?= $h($prefillTitle) ?>"
                   placeholder="Ex. Flash — découverte cache armes secteur Nord">
        </div>
    </section>

    <section class="sse-ed" id="sse-ed" data-doc-id="<?= $docId ?>" aria-label="Éditeur de document">
        <div class="sse-ed__class" id="sse-ed-class" role="status" data-level="<?= $h($prefillClass) ?>">
            <span class="sse-ed__class-tag">Classification</span>
            <strong><?= $h(mb_strtoupper($classifications[$prefillClass] ?? 'Confidentiel', 'UTF-8')) ?></strong>
            <span class="sse-ed__class-note">Ce document est classifié : ne le diffusez pas hors canal.</span>
        </div>

        <div class="sse-ed__bar" id="sse-ed-bar" role="toolbar" aria-label="Mise en forme du document" aria-controls="sse-ed-sheet">
            <div class="sse-ed__group" role="group" aria-label="Historique">
                <?= $btn('undo', 'Annuler (Ctrl+Z)', $ico('M5 4L2 7l3 3M2 7h7a4 4 0 010 8H6')) ?>
                <?= $btn('redo', 'Rétablir (Ctrl+Y)', $ico('M11 4l3 3-3 3M14 7H7a4 4 0 000 8h3')) ?>
            </div>
            <div class="sse-ed__group" role="group" aria-label="Style de paragraphe">
                <select class="sse-ed__select" data-cmd="block" aria-label="Style de paragraphe">
                    <option value="p">Corps de texte</option>
                    <option value="h2">Titre 1</option>
                    <option value="h3">Titre 2</option>
                    <option value="h4">Titre 3</option>
                    <option value="blockquote">Citation</option>
                </select>
            </div>
            <div class="sse-ed__group" role="group" aria-label="Caractères">
                <?= $btn('bold', 'Gras (Ctrl+B)', '<b aria-hidden="true">G</b>', true) ?>
                <?= $btn('italic', 'Italique (Ctrl+I)', '<i aria-hidden="true">I</i>', true) ?>
                <?= $btn('underline', 'Souligné (Ctrl+U)', '<u aria-hidden="true">S</u>', true) ?>
                <?= $btn('strike', 'Barré', '<s aria-hidden="true">B</s>', true) ?>
                <span class="sse-ed__pophost">
                    <button type="button" class="sse-ed__btn" title="Couleur du texte" aria-label="Couleur du texte" aria-haspopup="true" aria-expanded="false" aria-controls="sse-ed-pop-color"><span aria-hidden="true" class="sse-ed__sw-a">A</span></button>
                    <div class="sse-ed__pop" id="sse-ed-pop-color" role="group" aria-label="Couleur du texte" hidden>
                        <?php foreach ($paletteText as $c): ?>
                            <button type="button" class="sse-ed__sw" data-cmd="color" data-value="<?= $h($c) ?>" style="background:<?= $h($c) ?>" title="<?= $h($c) ?>" aria-label="Couleur <?= $h($c) ?>"></button>
                        <?php endforeach; ?>
                    </div>
                </span>
                <span class="sse-ed__pophost">
                    <button type="button" class="sse-ed__btn" title="Surlignage" aria-label="Surlignage" aria-haspopup="true" aria-expanded="false" aria-controls="sse-ed-pop-hilite"><span aria-hidden="true" class="sse-ed__sw-h">ab</span></button>
                    <div class="sse-ed__pop" id="sse-ed-pop-hilite" role="group" aria-label="Surlignage" hidden>
                        <?php foreach ($paletteMark as $c): ?>
                            <button type="button" class="sse-ed__sw" data-cmd="hilite" data-value="<?= $h($c) ?>" style="background:<?= $h($c) ?>" title="<?= $h($c) ?>" aria-label="Surlignage <?= $h($c) ?>"></button>
                        <?php endforeach; ?>
                    </div>
                </span>
                <?= $btn('clear', 'Effacer la mise en forme', $ico('M3 3l10 10M13 3L3 13')) ?>
            </div>
            <div class="sse-ed__group" role="group" aria-label="Listes et alignement">
                <?= $btn('ul', 'Liste à puces', $ico('M6 4h8M6 8h8M6 12h8M2.5 4h.5M2.5 8h.5M2.5 12h.5'), true) ?>
                <?= $btn('ol', 'Liste numérotée', $ico('M6 4h8M6 8h8M6 12h8M2 3.5h1.5v3M2 9.5h2l-2 2.5h2'), true) ?>
                <?= $btn('align-left', 'Aligner à gauche', $ico('M2 3h12M2 6.5h8M2 10h12M2 13.5h8'), true) ?>
                <?= $btn('align-center', 'Centrer', $ico('M2 3h12M4 6.5h8M2 10h12M4 13.5h8'), true) ?>
                <?= $btn('align-right', 'Aligner à droite', $ico('M2 3h12M6 6.5h8M2 10h12M6 13.5h8'), true) ?>
                <?= $btn('align-justify', 'Justifier', $ico('M2 3h12M2 6.5h12M2 10h12M2 13.5h12'), true) ?>
                <select class="sse-ed__select sse-ed__select--narrow" data-cmd="lineheight" aria-label="Interligne">
                    <option value="1">Interligne 1</option>
                    <option value="1.15">1,15</option>
                    <option value="1.5">1,5</option>
                    <option value="2">2</option>
                </select>
            </div>
            <div class="sse-ed__group" role="group" aria-label="Insertion">
                <span class="sse-ed__pophost">
                    <button type="button" class="sse-ed__btn" title="Tableau" aria-label="Tableau" aria-haspopup="true" aria-expanded="false" aria-controls="sse-ed-pop-table"><?= $ico('M2 3h12v10H2zM2 7h12M2 10h12M6 3v10M10 3v10') ?></button>
                    <div class="sse-ed__pop sse-ed__pop--table" id="sse-ed-pop-table" role="group" aria-label="Tableau" hidden>
                        <label for="sse-ed-rows">Lignes</label>
                        <input type="number" id="sse-ed-rows" min="1" max="20" value="3">
                        <label for="sse-ed-cols">Colonnes</label>
                        <input type="number" id="sse-ed-cols" min="1" max="8" value="3">
                        <button type="button" class="btn btn--sm" data-cmd="table-insert">Insérer le tableau</button>
                        <hr>
                        <button type="button" class="btn btn--ghost btn--sm" data-cmd="row-add">Ajouter une ligne</button>
                        <button type="button" class="btn btn--ghost btn--sm" data-cmd="row-del">Supprimer la ligne</button>
                        <button type="button" class="btn btn--ghost btn--sm" data-cmd="col-add">Ajouter une colonne</button>
                        <button type="button" class="btn btn--ghost btn--sm" data-cmd="col-del">Supprimer la colonne</button>
                        <button type="button" class="btn btn--ghost btn--sm" data-cmd="table-del">Supprimer le tableau</button>
                    </div>
                </span>
                <?= $btn('hr', 'Séparateur horizontal', $ico('M2 8h12')) ?>
                <?= $btn('pagebreak', 'Saut de page', $ico('M3 2v4h10V2M3 14v-4h10v4M1 8h2M6 8h4M13 8h2')) ?>
                <?= $btn('redact', 'Caviarder le passage sélectionné', '<span aria-hidden="true" class="sse-ed__redact-ico">▮▮</span>') ?>
                <select class="sse-ed__select sse-ed__select--narrow" data-cmd="field" aria-label="Insérer un champ du dossier">
                    <option value="">Champ du dossier…</option>
                    <option value="nom">Nom du rédacteur</option>
                    <option value="grade">Grade du rédacteur</option>
                    <option value="date">Date du jour</option>
                    <option value="dossier">Référence du dossier</option>
                    <option value="classification">Classification</option>
                </select>
                <?php if ($libraryEntries !== []): ?>
                    <button class="sse-ed__btn sse-ed__btn--text" type="button" id="sse-lib-btn" aria-haspopup="dialog">Mention</button>
                <?php endif; ?>
            </div>
            <div class="sse-ed__group sse-ed__group--end" role="group" aria-label="Sortie">
                <?= $btn('print', 'Imprimer la feuille', $ico('M4 6V2h8v4M4 12H2V6h12v6h-2M4 10h8v4H4z')) ?>
                <?php if ($isEdit && $docId > 0): ?>
                    <a class="sse-ed__btn sse-ed__btn--text" href="<?= $h(url('atak/sse/documents/' . $docId . '/pdf')) ?>" title="Exporter la dernière version enregistrée en PDF">PDF</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="sse-ed__notice" id="sse-ed-restore" role="alert" hidden>
            <span>Un brouillon automatique plus récent existe pour ce document.</span>
            <button type="button" class="btn btn--sm" id="sse-ed-restore-yes">Restaurer le brouillon</button>
            <button type="button" class="btn btn--ghost btn--sm" id="sse-ed-restore-no">Ignorer</button>
        </div>

        <?php if ($libraryEntries !== []): ?>
            <div class="sse-lib-suggest" id="sse-lib-suggest" hidden>
                <span class="sse-lib-suggest__label">Proposé pour ce document</span>
                <div class="sse-lib-suggest__row" id="sse-lib-suggest-row"></div>
            </div>
        <?php endif; ?>

        <div class="sse-ed__desk" id="sse-ed-desk">
            <div class="sse-ed__zoomer" id="sse-ed-zoomer" data-zoom="1">
                <div class="sse-ed__ruler" id="sse-ed-ruler" aria-hidden="true"></div>
                <div class="sse-ed__sheet" id="sse-ed-sheet" contenteditable="true" role="textbox" aria-multiline="true"
                     aria-label="Corps du document" spellcheck="true" lang="fr"><?= $bodyHtml ?></div>
            </div>
        </div>

        <div class="sse-ed__status">
            <span><span id="sse-ed-words">0</span> mots</span>
            <span><span id="sse-ed-pages">1</span> page(s) A4</span>
            <span id="sse-ed-save" role="status" aria-live="polite"></span>
            <label class="sse-ed__zoom" for="sse-ed-zoom">Zoom
                <select id="sse-ed-zoom">
                    <?php foreach ([50, 75, 100, 125, 150, 200] as $z): ?>
                        <option value="<?= $z ?>" <?= $z === 100 ? 'selected' : '' ?>><?= $z ?> %</option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <div id="sse-ed-msgs" hidden>
            <span data-msg="ready">Prêt.</span>
            <span data-msg="typing">Modifications non enregistrées…</span>
            <span data-msg="saved-local">Brouillon enregistré sur cet appareil</span>
            <span data-msg="saved-server">Brouillon enregistré sur le serveur</span>
            <span data-msg="empty">Le document est vide : rédigez le corps avant d’enregistrer.</span>
            <span data-msg="field-nom">Nom du rédacteur</span>
            <span data-msg="field-grade">Grade du rédacteur</span>
            <span data-msg="field-date"><?= $h(date('d/m/Y')) ?></span>
            <span data-msg="field-dossier">Référence du dossier</span>
            <span data-msg="field-classification">Classification</span>
        </div>
        <script type="application/json" id="sse-ed-cfg"><?= json_encode($edCfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
    </section>

    <textarea id="body" name="body" hidden aria-hidden="true" tabindex="-1"><?= $h(\App\Support\SseRichText::MARK . $bodyHtml) ?></textarea>
    <input type="hidden" name="inserted_mentions" id="sse-lib-trace" value="">
    <?php if ($libraryEntries !== []): ?>
        <p class="sse-lib-inserted" id="sse-lib-inserted" hidden>
            Mentions insérées : <span id="sse-lib-inserted-list"></span>.
            Le texte porté au document est conservé tel quel, même si la mention centrale est modifiée plus tard.
        </p>
    <?php endif; ?>
    <p class="muted sse-desk-footnote">
        Changez de type pour recharger le modèle guidé (si le corps n’a pas encore été personnalisé).
        Le passage caviardé devient une barre noire à l’affichage et dans le PDF, sa longueur reste visible.
        Évitez les noms en clair si une diffusion large est prévue.
        Pour une version expurgée à partir d’un dossier, utilisez aussi l’écran de déclassification.
    </p>

    <div class="toolbar-actions sse-desk-actions">
        <button class="btn" type="submit"><?= $isEdit ? 'Enregistrer' : 'Créer le brouillon' ?></button>
        <a class="btn btn--ghost" href="<?= $h($isEdit ? url('atak/sse/documents/' . $docId) : url('atak/sse/documents')) ?>">Annuler</a>
    </div>
</form>

<?php if ($libraryEntries !== []): ?>
<div class="sse-lib" id="sse-lib" hidden>
    <div class="sse-lib__backdrop" data-lib-close></div>
    <section class="sse-lib__panel" role="dialog" aria-modal="true" aria-labelledby="sse-lib-title">
        <header class="sse-lib__head">
            <div>
                <h2 id="sse-lib-title">Insérer une mention officielle</h2>
            </div>
            <button type="button" class="sse-lib__close" data-lib-close aria-label="Fermer la bibliothèque">×</button>
        </header>
        <div class="sse-lib__search">
            <label class="sr-only" for="sse-lib-q">Rechercher une mention</label>
            <input id="sse-lib-q" type="search" autocomplete="off"
                   placeholder="Chercher un code (RENS-03) ou un mot : recoupé, source, identité incertaine, caviardage…">
            <span class="sse-lib__count" id="sse-lib-count"></span>
        </div>
        <div class="sse-lib__body">
            <nav class="sse-lib__rail" aria-label="Familles de mentions">
                <button type="button" class="is-active" data-lib-cat="">Toutes</button>
                <?php foreach ($libraryCategories as $key => $label): ?>
                    <?php if (!isset($libraryUsedCategories[$key])) { continue; } ?>
                    <button type="button" data-lib-cat="<?= $h($key) ?>"><?= $h($label) ?></button>
                <?php endforeach; ?>
            </nav>
            <div class="sse-lib__list" id="sse-lib-list" tabindex="0"></div>
        </div>
        <footer class="sse-lib__foot">
            <span class="muted">
                Le texte inséré reste modifiable. Les variables sans valeur connue restent visibles entre accolades
                pour être complétées à la main.
            </span>
            <a class="link" href="<?= $h(url('atak/sse/bibliotheque')) ?>" target="_blank" rel="noopener">Administrer la bibliothèque</a>
        </footer>
    </section>
</div>
<?php endif; ?>

<script src="<?= $h(asset_url('assets/js/sse-doc-editor.js')) ?>"></script>
<script>
(function () {
    var typeLabels = <?= json_encode($typeLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?> || {};
    var hints = <?= json_encode($typeHints, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?> || {};
    var typeEl = document.getElementById('document_type');
    var titleEl = document.getElementById('title');
    var hintEl = document.getElementById('sse-type-hint');
    var ed = window.SseDocEditor;
    if (!typeEl || !ed) return;

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    typeEl.addEventListener('change', function () {
        if (hintEl) hintEl.textContent = hints[typeEl.value] || 'Choisissez le format adapté au destinataire.';
    });

    // ── Bibliothèque rédactionnelle ─────────────────────────────────────────
    var library = <?= json_encode($libraryEntries, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?> || [];
    var serverSuggestions = <?= json_encode($contextualSuggestions, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?> || [];
    var libRoot = document.getElementById('sse-lib');
    var traceEl = document.getElementById('sse-lib-trace');

    if (libRoot && library.length) {
        var libBtn = document.getElementById('sse-lib-btn');
        var listEl = document.getElementById('sse-lib-list');
        var searchEl = document.getElementById('sse-lib-q');
        var countEl = document.getElementById('sse-lib-count');
        var railBtns = libRoot.querySelectorAll('[data-lib-cat]');
        var suggestBox = document.getElementById('sse-lib-suggest');
        var suggestRow = document.getElementById('sse-lib-suggest-row');
        var insertedBox = document.getElementById('sse-lib-inserted');
        var insertedList = document.getElementById('sse-lib-inserted-list');
        var activeCat = '';
        var inserted = [];
        var lastFocus = null;

        var byCode = {};
        library.forEach(function (m) { byCode[m.code] = m; });

        // Ce que le document raconte oriente la mention proposée : une identité non
        // confirmée appelle PERS-01, une contradiction EXP-04, etc.
        var rules = [
            { test: /identit[ée]\s+(non\s+confirm|incertaine|suppos)/i, codes: ['PERS-01', 'ALERT-01', 'MENT-05'] },
            { test: /homonym/i, codes: ['PERS-03'] },
            { test: /contradict|diverg|incoh[ée]ren/i, codes: ['EXP-04', 'ALERT-04'] },
            { test: /source\s+unique/i, codes: ['SRC-02', 'ALERT-02'] },
            { test: /non\s+recoup|sans\s+recoupement/i, codes: ['RENS-01', 'EXP-03', 'ALERT-03'] },
            { test: /recoup[ée]/i, codes: ['RENS-03', 'RENS-02'] },
            { test: /hypoth[èe]se/i, codes: ['RENS-04', 'NOTE-01'] },
            { test: /d[ée]riv[ée]|extrait\s+de/i, codes: ['PIECE-03'] },
            { test: /empreinte|int[ée]grit[ée]|hash/i, codes: ['PIECE-04', 'ALERT-11'] },
            { test: /caviard|expurg|masqu[ée]/i, codes: ['CAV-01', 'DIFF-02'] },
            { test: /\[\[/, codes: ['CAV-01', 'CAV-02'] },
            { test: /site|implantation|b[âa]timent/i, codes: ['SITE-02', 'SITE-03'] },
            { test: /t[ée]l[ée]phone|imei|num[ée]ro\s+appel|trafic/i, codes: ['COM-01', 'COM-02'] },
            { test: /empreintes?\s+digitales?|pr[ée]l[èe]vement|adn/i, codes: ['BIO-01', 'BIO-02'] },
            { test: /arme|munition|explosif/i, codes: ['MAT-05'] },
            { test: /v[ée]hicule|plaque/i, codes: ['MAT-06'] },
            { test: /clos|cl[ôo]tur/i, codes: ['ARCH-03', 'ARCH-01'] },
            { test: /diffus/i, codes: ['DIFF-01', 'DIFF-06'] }
        ];
        var typeCodes = {
            flash: ['RENS-01', 'ALERT-03', 'EXP-01'],
            compte_rendu: ['PIECE-01', 'EXP-01', 'CLASS-01'],
            note_analyse: ['NOTE-02', 'RENS-04', 'METH-01'],
            synthese: ['METH-01', 'NOTE-04', 'CLASS-01'],
            diffusion: ['DIFF-02', 'CAV-01', 'DIFF-03']
        };

        function normalize(s) {
            return String(s || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }

        function matches(entry, needle) {
            if (!needle) return true;
            return normalize(entry.code + ' ' + entry.title + ' ' + entry.content + ' ' + entry.category_label)
                .indexOf(needle) !== -1;
        }

        function preview(text) {
            var flat = String(text).replace(/\s+/g, ' ').trim();
            return flat.length > 220 ? flat.slice(0, 217) + '…' : flat;
        }

        function renderList() {
            var needle = normalize(searchEl.value.trim());
            var rows = library.filter(function (m) {
                return (!activeCat || m.category === activeCat) && matches(m, needle);
            });
            countEl.textContent = rows.length + (rows.length > 1 ? ' mentions' : ' mention');
            if (!rows.length) {
                listEl.innerHTML = '<p class="sse-lib__empty">Aucune mention ne correspond. '
                    + 'Essayez un code (RENS-03) ou un mot du texte recherché.</p>';
                return;
            }
            var html = '';
            var lastCat = null;
            rows.forEach(function (m) {
                if (m.category !== lastCat) {
                    lastCat = m.category;
                    html += '<p class="sse-lib__cat">' + esc(m.category_label) + '</p>';
                }
                html += '<article class="sse-lib__item">'
                    + '<div class="sse-lib__item-head">'
                    + '<span class="sse-lib__code">' + esc(m.code) + '</span>'
                    + '<strong>' + esc(m.title) + '</strong>'
                    + '<span class="sse-lib__ctx">' + esc(m.context_label) + '</span>'
                    + '</div>'
                    + '<p class="sse-lib__text">' + esc(preview(m.content)) + '</p>'
                    + '<div class="sse-lib__item-foot">'
                    + (m.variables && m.variables.length
                        ? '<span class="muted">Variables : ' + esc(m.variables.join(', ')) + '</span>'
                        : '<span class="muted">Texte fixe</span>')
                    + '<button type="button" class="btn btn--sm" data-lib-insert="' + esc(m.code) + '">Insérer</button>'
                    + '</div></article>';
            });
            listEl.innerHTML = html;
        }

        function renderSuggestions() {
            if (!suggestBox || !suggestRow) return;
            var text = ed.text() + ' ' + titleEl.value;
            var codes = (typeCodes[typeEl.value] || []).slice();
            (serverSuggestions || []).forEach(function (s) {
                if (s && s.code) codes.unshift(s.code);
            });
            rules.forEach(function (rule) {
                if (rule.test.test(text)) {
                    rule.codes.forEach(function (c) { codes.push(c); });
                }
            });
            var seen = {};
            var picked = [];
            codes.forEach(function (c) {
                if (seen[c] || !byCode[c]) return;
                if (inserted.some(function (i) { return i.code === c; })) return;
                seen[c] = true;
                picked.push(byCode[c]);
            });
            picked = picked.slice(0, 8);
            if (!picked.length) {
                suggestBox.hidden = true;
                suggestRow.innerHTML = '';
                return;
            }
            suggestBox.hidden = false;
            suggestRow.innerHTML = picked.map(function (m) {
                var reason = '';
                (serverSuggestions || []).forEach(function (s) {
                    if (s.code === m.code && s.reason) reason = s.reason;
                });
                return '<button type="button" class="sse-lib-chip" data-lib-insert="' + esc(m.code) + '" '
                    + 'title="' + esc(reason || preview(m.content)) + '">'
                    + '<span>' + esc(m.code) + '</span>' + esc(m.title)
                    + (reason ? '<small>' + esc(reason) + '</small>' : '')
                    + '</button>';
            }).join('');
        }

        function renderInserted() {
            traceEl.value = inserted.length ? JSON.stringify(inserted) : '';
            if (!insertedBox || !insertedList) return;
            if (!inserted.length) {
                insertedBox.hidden = true;
                insertedList.textContent = '';
                return;
            }
            insertedBox.hidden = false;
            insertedList.textContent = inserted.map(function (i) {
                return i.code + ' (v' + i.version + ')';
            }).join(', ');
        }

        function insertMention(code) {
            var mention = byCode[code];
            if (!mention) return;
            ed.insertText(mention.content);

            inserted.push({ code: mention.code, version: mention.version, text: mention.content });
            renderInserted();
            renderSuggestions();
        }

        function openLibrary() {
            lastFocus = document.activeElement;
            libRoot.hidden = false;
            document.body.classList.add('sse-lib-open');
            renderList();
            searchEl.value = '';
            window.setTimeout(function () { searchEl.focus(); }, 20);
        }

        function closeLibrary() {
            libRoot.hidden = true;
            document.body.classList.remove('sse-lib-open');
            if (lastFocus && lastFocus.focus) lastFocus.focus();
        }

        if (libBtn) libBtn.addEventListener('click', openLibrary);

        libRoot.addEventListener('click', function (e) {
            if (e.target.closest('[data-lib-close]')) {
                closeLibrary();
                return;
            }
            var insertBtn = e.target.closest('[data-lib-insert]');
            if (insertBtn) {
                insertMention(insertBtn.getAttribute('data-lib-insert'));
                closeLibrary();
                return;
            }
            var catBtn = e.target.closest('[data-lib-cat]');
            if (catBtn) {
                activeCat = catBtn.getAttribute('data-lib-cat') || '';
                Array.prototype.forEach.call(railBtns, function (b) {
                    b.classList.toggle('is-active', b === catBtn);
                });
                renderList();
            }
        });

        if (suggestRow) {
            suggestRow.addEventListener('click', function (e) {
                var chip = e.target.closest('[data-lib-insert]');
                if (chip) insertMention(chip.getAttribute('data-lib-insert'));
            });
        }

        searchEl.addEventListener('input', renderList);
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !libRoot.hidden) closeLibrary();
        });

        ed.onChange(renderSuggestions);
        ['input', 'change'].forEach(function (ev) {
            titleEl.addEventListener(ev, renderSuggestions);
            typeEl.addEventListener(ev, renderSuggestions);
        });

        renderList();
        renderSuggestions();
        renderInserted();
    }


})();
</script>
<?php if ($isEdit) { \App\Services\Sse\Attach\SseAttachWindow::render(); } ?>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
