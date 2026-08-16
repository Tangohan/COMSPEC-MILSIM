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
$prefillType = (string) ($prefillType ?? ($document['document_type'] ?? 'note_analyse'));
$prefillCaseId = (int) ($prefillCaseId ?? ($document['case_id'] ?? 0));
$prefillTitle = (string) ($prefillTitle ?? ($document['title'] ?? ''));
$prefillBody = (string) ($prefillBody ?? ($document['body'] ?? ''));
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

$previewDoc = [
    'reference_code' => $isEdit ? (string) ($document['reference_code'] ?? 'DOC-····') : 'DOC-···· (brouillon)',
    'title' => $prefillTitle,
    'body' => $prefillBody,
    'classification' => $prefillClass,
    'classification_label' => $classifications[$prefillClass] ?? 'Confidentiel',
    'status' => $prefillStatus,
    'status_label' => $statusLabels[$prefillStatus] ?? 'Brouillon',
    'document_type' => $prefillType,
    'document_type_label' => $typeLabels[$prefillType] ?? 'Document',
    'author_label' => $isEdit ? (string) ($document['author_label'] ?? $authorPreview) : $authorPreview,
    'case_reference' => '',
    'created_at' => date('Y-m-d H:i:s'),
];
foreach ($cases as $c) {
    if ((int) ($c['id'] ?? 0) === $prefillCaseId && empty($c['is_folder'])) {
        $previewDoc['case_reference'] = (string) (($c['reference_code'] ?? '') . ' — ' . ($c['title'] ?? ''));
        break;
    }
}
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
            Rédigez à gauche ; l’aperçu papier à droite reprend la présentation officielle.
            Conservez la classification au niveau réel du contenu — la diffusion plus large
            se fait ensuite via caviardage et validation.
        </p>
    </div>
</section>

<form method="post" action="<?= $h($action) ?>" class="sse-doc-workspace" id="sse-doc-form">
    <?= \App\Core\Csrf::field() ?>

    <section class="panel sse-doc-workspace__editor sse-desk-panel sse-desk-editor">
        <div class="panel-header">
            <div class="panel-title">
                <span class="panel-index">19.11</span>
                Fiche document
            </div>
            <div class="panel-meta"><?= $isEdit ? 'Mise à jour' : 'Création' ?></div>
        </div>
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
                                data-label="<?= $h(($c['reference_code'] ?? '') . ' — ' . ($c['title'] ?? '')) ?>">
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
            <div class="sse-desk-tools">
                <button class="btn btn--ghost btn--sm" type="button" id="sse-mask-btn">
                    Masquer le passage sélectionné
                </button>
                <span class="muted">Le passage devient une barre noire sur la feuille, sa longueur reste visible.</span>
            </div>
            <div class="sse-desk-paper">
                <textarea id="body" name="body" rows="26" required
                          placeholder="Structurez le produit : situation, faits, analyse, recommandations."><?= $h($prefillBody) ?></textarea>
            </div>
            <p class="muted sse-desk-footnote">
                Changez de type pour recharger le modèle guidé (si le corps n’a pas encore été personnalisé).
                Évitez les noms en clair si une diffusion large est prévue.
                Pour une version expurgée à partir d’un dossier, utilisez aussi l’écran de déclassification.
            </p>

            <div class="toolbar-actions sse-desk-actions">
                <button class="btn" type="submit"><?= $isEdit ? 'Enregistrer' : 'Créer le brouillon' ?></button>
                <a class="btn btn--ghost" href="<?= $h($isEdit ? url('atak/sse/documents/' . (int) $document['id']) : url('atak/sse/documents')) ?>">Annuler</a>
            </div>
        </div>
    </section>

    <aside class="sse-doc-workspace__preview" aria-label="Aperçu papier">
        <div class="sse-doc-preview-label">Aperçu officiel</div>
        <?php
        $document = $previewDoc;
        $livePreview = true;
        require __DIR__ . '/partials/document_paper.php';
        ?>
    </aside>
</form>

<script>
(function () {
    var templates = <?= json_encode($bodyTemplates, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?> || {};
    var typeLabels = <?= json_encode($typeLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?> || {};
    var classLabels = <?= json_encode($classifications, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?> || {};
    var statusLabels = <?= json_encode($statusLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?> || {};
    var hints = <?= json_encode($typeHints, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?> || {};
    var typeEl = document.getElementById('document_type');
    var classEl = document.getElementById('classification');
    var statusEl = document.getElementById('status');
    var titleEl = document.getElementById('title');
    var bodyEl = document.getElementById('body');
    var caseEl = document.getElementById('case_id');
    var hintEl = document.getElementById('sse-type-hint');
    var paper = document.querySelector('[data-sse-doc-paper]');
    if (!typeEl || !bodyEl || !paper) return;

    var lastTemplate = bodyEl.value;
    var bodyMount = document.getElementById('sse-doc-paper-body');

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function escMasked(s) {
        return esc(s).replace(/\[\[(.*?)\]\]/g, function (all, inner) {
            var raw = String(inner).trim();
            var sized = raw.match(/^#(\d{1,3})$/);
            var width = sized ? parseInt(sized[1], 10) : raw.length;
            width = Math.max(3, Math.min(90, width));
            return '<span class="sse-doc-paper__redact" style="display:inline-block;width:'
                + width + 'ch;height:0.95em;background:#0b1220"></span>';
        });
    }

    function bodyToHtml(raw) {
        var lines = String(raw || '').replace(/\r\n|\r/g, '\n').split('\n');
        var out = [];
        var titles = {
            'FLASH RENSEIGNEMENT': 1,
            'COMPTE RENDU D’EXPLOITATION': 1,
            "COMPTE RENDU D'EXPLOITATION": 1,
            'NOTE D’ANALYSE': 1,
            "NOTE D'ANALYSE": 1,
            'SYNTHÈSE DE SITUATION': 1,
            'SYNTHESE DE SITUATION': 1,
            'VERSION DE DIFFUSION': 1
        };
        lines.forEach(function (line) {
            var trim = line.replace(/\s+$/g, '');
            var t = trim.trim();
            if (!t) { out.push('<p class="sse-doc-paper__spacer">&nbsp;</p>'); return; }
            if (/^[═─\-_=]{6,}$/.test(t)) { out.push('<hr class="sse-doc-paper__rule">'); return; }
            if (t === '—' || t === '--' || t === '-' || t === '• —') {
                out.push('<p class="sse-doc-paper__fill">………………………………………………………………</p>');
                return;
            }
            var up = t.toUpperCase();
            if (titles[up] || titles[t]) {
                out.push('<h1 class="sse-doc-paper__doc-title">' + escMasked(t) + '</h1>');
                return;
            }
            if (/^\d+\.\s+.+/.test(t) || /^AVERTISSEMENT$/i.test(t)
                || (t === up && t.length < 80 && t.indexOf(':') === -1 && t.charAt(0) !== '─')) {
                out.push('<h2 class="sse-doc-paper__section">' + escMasked(t) + '</h2>');
                return;
            }
            if (/^.+:\s*$/.test(t) && t.length < 90) {
                out.push('<p class="sse-doc-paper__label">' + escMasked(t) + '</p>');
                return;
            }
            if (t.indexOf('• ') === 0 || t.indexOf('- ') === 0 || /^H\d+\s/.test(t)) {
                out.push('<p class="sse-doc-paper__bullet">' + escMasked(t) + '</p>');
                return;
            }
            out.push('<p class="sse-doc-paper__p">' + escMasked(t) + '</p>');
        });
        return out.join('\n') || '<p class="sse-doc-paper__muted">Le corps du document apparaîtra ici.</p>';
    }

    function syncMeta() {
        if (hintEl) {
            hintEl.textContent = hints[typeEl.value] || 'Choisissez le format adapté au destinataire.';
        }
        var wm = paper.querySelector('.sse-doc-paper__watermark');
        var classLab = classLabels[classEl.value] || classEl.options[classEl.selectedIndex].text;
        Array.prototype.forEach.call(
            paper.querySelectorAll('.sse-doc-paper__banner strong'),
            function (el) { el.textContent = String(classLab).toUpperCase(); }
        );
        Array.prototype.forEach.call(
            paper.querySelectorAll('.sse-doc-paper__stamp--class'),
            function (el) { el.textContent = String(classLab).toUpperCase(); }
        );
        if (wm) wm.textContent = String(classLab).toUpperCase();

        var typeLab = typeLabels[typeEl.value] || typeEl.options[typeEl.selectedIndex].text;
        var statusLab = statusLabels[statusEl.value] || statusEl.options[statusEl.selectedIndex].text;
        var refno = paper.querySelector('.sse-doc-paper__refno');
        if (refno) {
            var ref = <?= json_encode($previewDoc['reference_code'], JSON_UNESCAPED_UNICODE) ?>;
            refno.textContent = 'N° ' + ref + ' / SSE / ' + String(typeLab).toUpperCase();
        }
        var meta = paper.querySelector('.sse-doc-paper__meta-right');
        if (meta && meta.children[1]) {
            meta.children[1].textContent = typeLab + ' · ' + statusLab;
        }
        var refs = paper.querySelector('.sse-doc-paper__refs');
        if (refs) {
            var title = (titleEl.value || '').trim();
            var caseOpt = caseEl.options[caseEl.selectedIndex];
            var caseLab = caseEl.value ? (caseOpt.getAttribute('data-label') || caseOpt.text) : '';
            var html = title
                ? '<p><span>OBJET</span> : ' + esc(title) + '</p>'
                : '<p><span>OBJET</span> : <em class="sse-doc-paper__muted">(intitulé à renseigner)</em></p>';
            html += '<p><span>RÉFÉRENCE</span> : ' + esc(<?= json_encode($previewDoc['reference_code'], JSON_UNESCAPED_UNICODE) ?>) + '</p>';
            if (caseLab) html += '<p><span>DOSSIER</span> : ' + esc(caseLab) + '</p>';
            refs.innerHTML = html;
        }
        if (bodyMount) bodyMount.innerHTML = bodyToHtml(bodyEl.value);
    }

    typeEl.addEventListener('change', function () {
        var next = templates[typeEl.value] || '';
        if (!next) { syncMeta(); return; }
        var cur = bodyEl.value;
        var untouched = !cur.trim() || cur === lastTemplate
            || Object.keys(templates).some(function (k) { return templates[k] === cur; });
        if (untouched) {
            bodyEl.value = next;
            lastTemplate = next;
        }
        syncMeta();
    });

    var maskBtn = document.getElementById('sse-mask-btn');
    if (maskBtn) {
        maskBtn.addEventListener('click', function () {
            var start = bodyEl.selectionStart;
            var end = bodyEl.selectionEnd;
            if (start === end) {
                bodyEl.focus();
                return;
            }
            var selected = bodyEl.value.slice(start, end);
            bodyEl.value = bodyEl.value.slice(0, start) + '[[' + selected + ']]' + bodyEl.value.slice(end);
            bodyEl.focus();
            bodyEl.setSelectionRange(start, end + 4);
            syncMeta();
        });
    }

    ['input', 'change'].forEach(function (ev) {
        titleEl.addEventListener(ev, syncMeta);
        bodyEl.addEventListener(ev, syncMeta);
        classEl.addEventListener(ev, syncMeta);
        statusEl.addEventListener(ev, syncMeta);
        caseEl.addEventListener(ev, syncMeta);
    });

    syncMeta();
})();
</script>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
