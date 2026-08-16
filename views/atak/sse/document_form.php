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
                <?php if ($libraryEntries !== []): ?>
                    <button class="btn btn--sm" type="button" id="sse-lib-btn" aria-haspopup="dialog">
                        Insérer une mention
                    </button>
                <?php endif; ?>
                <span class="muted">Le passage masqué devient une barre noire sur la feuille, sa longueur reste visible.</span>
            </div>
            <?php if ($libraryEntries !== []): ?>
                <div class="sse-lib-suggest" id="sse-lib-suggest" hidden>
                    <span class="sse-lib-suggest__label">Proposé pour ce document</span>
                    <div class="sse-lib-suggest__row" id="sse-lib-suggest-row"></div>
                </div>
            <?php endif; ?>
            <div class="sse-desk-paper">
                <textarea id="body" name="body" rows="26" required
                          placeholder="Structurez le produit : situation, faits, analyse, recommandations."><?= $h($prefillBody) ?></textarea>
            </div>
            <input type="hidden" name="inserted_mentions" id="sse-lib-trace" value="">
            <?php if ($libraryEntries !== []): ?>
                <p class="sse-lib-inserted" id="sse-lib-inserted" hidden>
                    Mentions insérées : <span id="sse-lib-inserted-list"></span>.
                    Le texte porté au document est conservé tel quel, même si la mention centrale est modifiée plus tard.
                </p>
            <?php endif; ?>
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

    <?php if ($libraryEntries !== []): ?>
    <div class="sse-lib" id="sse-lib" hidden>
        <div class="sse-lib__backdrop" data-lib-close></div>
        <section class="sse-lib__panel" role="dialog" aria-modal="true" aria-labelledby="sse-lib-title">
            <header class="sse-lib__head">
                <div>
                    <p class="sse-lib__kicker">Bibliothèque rédactionnelle</p>
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
            var text = bodyEl.value + ' ' + titleEl.value;
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
            var value = bodyEl.value;
            var pos = typeof bodyEl.selectionStart === 'number' ? bodyEl.selectionStart : value.length;
            var before = value.slice(0, pos);
            var after = value.slice(pos);
            var lead = (before === '' || /\n\s*\n$/.test(before)) ? '' : (/\n$/.test(before) ? '\n' : '\n\n');
            var tail = (after === '' || /^\s*\n/.test(after)) ? '\n' : '\n\n';
            var block = lead + mention.content + tail;

            bodyEl.value = before + block + after;
            var caret = pos + block.length;
            bodyEl.focus();
            bodyEl.setSelectionRange(caret, caret);

            inserted.push({ code: mention.code, version: mention.version, text: mention.content });
            renderInserted();
            syncMeta();
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

        ['input', 'change'].forEach(function (ev) {
            bodyEl.addEventListener(ev, renderSuggestions);
            titleEl.addEventListener(ev, renderSuggestions);
            typeEl.addEventListener(ev, renderSuggestions);
        });

        renderList();
        renderSuggestions();
        renderInserted();
    }

    syncMeta();
})();
</script>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
