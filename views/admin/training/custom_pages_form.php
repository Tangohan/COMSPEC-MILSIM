<?php
declare(strict_types=1);
$page = isset($customPage) && is_array($customPage) ? $customPage : null;
$isEdit = $page !== null;
$action = $isEdit
    ? training_lms_admin_url('pages-html/' . (int) ($page['id'] ?? 0))
    : training_lms_admin_url('pages-html');
$chaptersInit = \App\Support\TrainingFormationCustomPageRenderer::decodeSections(isset($page['sections_json']) ? (string) $page['sections_json'] : null);
$isHandbook = $chaptersInit !== [];
$sectionsJsonInitial = json_encode($chaptersInit, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS);
$docCssPublic = rtrim(url(''), '/') . '/assets/css/training_formation_doc.css';
?>
<div id="cp-editor-root" data-initial-handbook="<?= $isHandbook ? '1' : '0' ?>">
<section class="tc-panel p-6 md:p-8">
    <p class="tc-kicker">Pilotage des formations</p>
    <div class="flex flex-wrap items-start justify-between gap-4 mb-4">
        <div class="min-w-0">
            <h1 class="tc-hero-title mb-2"><?= $isEdit ? 'Modifier la documentation' : 'Nouvelle documentation' ?></h1>
            <p class="text-sm text-slate-600 max-w-3xl leading-relaxed">
                Rédigez des <strong class="font-semibold text-slate-800">documentations HTML publiables</strong> : même exigence de fond qu’un parcours LMS (texte structuré, illustrations, liens), dans un <strong class="font-semibold text-slate-800">style lecture / manuel</strong> lisible à l’écran — <strong class="font-semibold text-slate-800">sans inscription, sans progression ni quiz</strong>, à la place d’un PDF figé.
                Une URL par document (<code class="text-xs bg-slate-100 px-1 rounded">/formations/page/…</code>) une fois publié.
            </p>
        </div>
        <a href="<?= htmlspecialchars(training_lms_admin_url('pages-html')) ?>" class="shrink-0 text-sm font-semibold text-emerald-700 hover:underline">← Retour à la liste</a>
    </div>
</section>

<section class="rounded-2xl border border-slate-200 bg-white p-6 md:p-8" aria-labelledby="cp-form-heading">
    <h2 id="cp-form-heading" class="text-sm font-black uppercase tracking-[0.2em] text-slate-600 mb-1">Contenu</h2>
    <p class="text-xs text-slate-500 mb-6 max-w-3xl leading-relaxed">
        Mode <strong class="text-slate-700">une page</strong> : un seul flux HTML (livret d’une vue). Mode <strong class="text-slate-700">manuel</strong> : introduction optionnelle + chapitres numérotés avec sommaire automatique — équivalent à plusieurs « leçons » regroupées sous une même adresse.
        L’aperçu se met à jour pendant la saisie ; le mode « feuillet » ne modifie pas l’enregistrement.
    </p>

    <form method="post" action="<?= htmlspecialchars($action) ?>" class="space-y-8" id="cp-main-form">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="sections_json" id="cp-sections-json" value="<?= htmlspecialchars($sectionsJsonInitial, ENT_QUOTES, 'UTF-8') ?>">

        <div class="grid gap-8 lg:grid-cols-12">
            <div class="lg:col-span-4 space-y-5">
                <div class="rounded-xl border border-slate-100 bg-slate-50/80 p-5">
                    <h3 class="text-xs font-black uppercase tracking-wide text-slate-700 mb-4">Réglages</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wide text-slate-600 mb-1" for="cp-title">Titre affiché sur le site</label>
                            <input type="text" name="title" id="cp-title" required maxlength="255" value="<?= htmlspecialchars((string) ($page['title'] ?? '')) ?>" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="ex. Doctrine S1 — guide HTML">
                            <p class="text-[11px] text-slate-500 mt-1">Onglet du navigateur et titre d’en-tête sur la page publique.</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wide text-slate-600 mb-1" for="cp-slug">Adresse courte</label>
                            <input type="text" name="slug" id="cp-slug" required maxlength="120" pattern="[a-z0-9-]+" value="<?= htmlspecialchars((string) ($page['slug'] ?? '')) ?>" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-mono" placeholder="ex. doctrine-s1-html">
                            <p class="text-[11px] text-slate-500 mt-1.5 leading-relaxed">Lettres minuscules, chiffres et tirets. Lien : <code class="bg-white border border-slate-200 px-1 rounded text-[11px]">/formations/page/<span id="cp-slug-preview"><?= htmlspecialchars((string) ($page['slug'] ?? 'votre-id')) ?></span></code></p>
                        </div>
                        <fieldset class="rounded-lg border border-slate-200 bg-white px-3 py-3 space-y-2">
                            <legend class="text-xs font-bold uppercase tracking-wide text-slate-600 px-1">Structure</legend>
                            <label class="flex items-start gap-2 cursor-pointer">
                                <input type="radio" name="doc_structure" value="single" class="mt-1 rounded border-slate-300 text-emerald-600"<?= !$isHandbook ? ' checked' : '' ?>>
                                <span class="text-sm text-slate-800"><span class="font-semibold">Une page HTML</span><span class="block text-[11px] text-slate-500 font-normal mt-0.5">Un seul corps (équivalent d’un PDF d’une page ou d’un export unique).</span></span>
                            </label>
                            <label class="flex items-start gap-2 cursor-pointer">
                                <input type="radio" name="doc_structure" value="handbook" class="mt-1 rounded border-slate-300 text-emerald-600"<?= $isHandbook ? ' checked' : '' ?>>
                                <span class="text-sm text-slate-800"><span class="font-semibold">Manuel à chapitres</span><span class="block text-[11px] text-slate-500 font-normal mt-0.5">Sommaire cliquable, plusieurs parties — comme un parcours rédigé chapitre par chapitre, sans quiz ni suivi.</span></span>
                            </label>
                        </fieldset>
                        <div class="flex items-start gap-3 rounded-lg border border-emerald-100 bg-emerald-50/50 px-3 py-3">
                            <input type="checkbox" name="is_published" id="cp-pub" value="1" class="mt-0.5 rounded border-slate-300 text-emerald-600" <?= !empty($page['is_published']) ? ' checked' : '' ?>>
                            <div>
                                <label for="cp-pub" class="text-sm font-semibold text-slate-900 cursor-pointer">Publier</label>
                                <p class="text-[11px] text-slate-600 mt-0.5 leading-relaxed">Les membres connectés peuvent ouvrir l’URL. Sinon brouillon (prévisualisation staff toujours possible).</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-8 space-y-4">
                <div id="cp-handbook-panel" class="space-y-4 <?= $isHandbook ? '' : 'hidden' ?>">
                    <div class="rounded-xl border border-violet-100 bg-violet-50/60 p-4">
                        <p class="text-xs font-bold uppercase tracking-wide text-violet-900 mb-2">Chapitres du manuel</p>
                        <p class="text-[11px] text-violet-950/80 leading-relaxed mb-3">Chaque chapitre devient une section sur la page publique, avec ancre dans le sommaire. L’introduction ci-dessous est optionnelle (texte avant le premier chapitre).</p>
                        <div id="cp-chapters-root" class="space-y-4">
                            <?php foreach ($chaptersInit as $ch):
                                $ct = htmlspecialchars((string) ($ch['title'] ?? ''), ENT_QUOTES, 'UTF-8');
                                $chHtml = htmlspecialchars((string) ($ch['html'] ?? ''), ENT_QUOTES, 'UTF-8');
                                $csl = htmlspecialchars((string) ($ch['slug'] ?? ''), ENT_QUOTES, 'UTF-8');
                                ?>
                            <div class="cp-chapter rounded-xl border border-slate-200 bg-white p-4 space-y-2 shadow-sm">
                                <div class="flex flex-wrap gap-2 justify-between items-center">
                                    <label class="text-[10px] font-black uppercase tracking-wide text-slate-500">Titre du chapitre</label>
                                    <div class="flex flex-wrap gap-1">
                                        <button type="button" class="text-[10px] font-bold uppercase tracking-wide text-slate-600 border border-slate-200 rounded-lg px-2 py-1 bg-white hover:bg-slate-50" data-cp-chapter-up>Monter</button>
                                        <button type="button" class="text-[10px] font-bold uppercase tracking-wide text-slate-600 border border-slate-200 rounded-lg px-2 py-1 bg-white hover:bg-slate-50" data-cp-chapter-down>Descendre</button>
                                        <button type="button" class="text-[10px] font-bold uppercase tracking-wide text-rose-700 border border-rose-200 rounded-lg px-2 py-1 bg-rose-50 hover:bg-rose-100" data-cp-chapter-remove>Retirer</button>
                                    </div>
                                </div>
                                <input type="text" class="cp-chapter-title w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" maxlength="255" value="<?= $ct ?>" placeholder="Titre affiché dans le sommaire">
                                <input type="hidden" class="cp-chapter-slug" value="<?= $csl ?>">
                                <label class="text-[10px] font-black uppercase tracking-wide text-slate-500">Corps HTML du chapitre</label>
                                <textarea class="cp-chapter-html w-full rounded-lg border border-slate-200 bg-slate-50/50 px-3 py-2 text-sm font-mono min-h-[10rem]" rows="10" placeholder="Balises HTML autorisées (titres, listes, tableaux, liens…)"><?= $chHtml ?></textarea>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" id="cp-chapter-add" class="mt-3 tc-btn-primary tc-btn-ghost text-xs inline-flex items-center">+ Ajouter un chapitre</button>
                    </div>
                </div>

                <div id="cp-non-handbook-chrome" class="space-y-4 <?= $isHandbook ? 'hidden' : '' ?>">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <span id="cp-detect-badge" class="inline-flex items-center rounded-full border border-slate-200 bg-slate-100 px-3 py-1 text-[11px] font-bold uppercase tracking-wide text-slate-700">…</span>
                        </div>
                        <label class="inline-flex items-center gap-2 cursor-pointer select-none text-sm text-slate-700">
                            <input type="checkbox" id="cp-livret-preview" class="rounded border-slate-300 text-emerald-600">
                            <span class="font-medium">Aperçu façon feuillet</span>
                        </label>
                    </div>
                <div id="cp-mobile-tablist" class="flex gap-2 md:hidden mb-1" role="tablist" aria-label="Mode d’affichage de l’éditeur">
                    <button type="button" class="flex-1 rounded-xl border border-slate-200 px-3 py-2.5 text-xs font-black uppercase tracking-wide transition-colors bg-white shadow-sm text-slate-900" data-cp-tab="code" role="tab" aria-selected="true">Code source</button>
                    <button type="button" class="flex-1 rounded-xl border border-slate-200 px-3 py-2.5 text-xs font-black uppercase tracking-wide transition-colors text-slate-500" data-cp-tab="preview" role="tab" aria-selected="false">Aperçu</button>
                </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2 md:gap-5">
                    <div id="cp-panel-code" class="min-w-0 space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <label class="text-xs font-black uppercase tracking-wide text-slate-700" for="cp-html" id="cp-html-label">Corps HTML</label>
                        </div>
                        <p class="text-[11px] text-slate-500 leading-relaxed" id="cp-html-hint">Mode une page : tout le contenu ici. Mode manuel : texte d’introduction optionnel avant les chapitres.</p>
                        <textarea name="html_body" id="cp-html" rows="20" class="w-full min-h-[22rem] md:min-h-[32rem] rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-3 text-sm font-mono leading-relaxed text-slate-900 shadow-inner focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-400"><?= htmlspecialchars((string) ($page['html_body'] ?? '')) ?></textarea>
                    </div>
                    <div id="cp-panel-preview" class="min-w-0 flex flex-col gap-2 hidden md:flex">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="text-xs font-black uppercase tracking-wide text-slate-700">Aperçu</span>
                            <button type="button" id="cp-open-preview-tab" class="text-xs font-bold text-emerald-700 hover:underline bg-transparent border-0 cursor-pointer p-0">
                                Ouvrir l’aperçu dans un nouvel onglet
                            </button>
                        </div>
                        <div class="relative flex-1 min-h-[22rem] md:min-h-[32rem] rounded-xl border border-slate-200 bg-slate-100 overflow-hidden shadow-inner">
                            <iframe id="cp-preview-frame" title="Aperçu du contenu" class="absolute inset-0 w-full h-full bg-white" sandbox="allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox allow-downloads"></iframe>
                        </div>
                        <p class="text-[11px] text-slate-500 leading-relaxed">Ne collez que du contenu de confiance : le HTML est enregistré tel quel. Prévisualisation complète (y compris brouillon) : lien « Prévisualiser » dans la liste.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3 pt-4 border-t border-slate-100">
            <button type="submit" class="tc-btn-primary tc-btn-emerald text-sm">Enregistrer</button>
            <a href="<?= htmlspecialchars(training_lms_admin_url('pages-html')) ?>" class="tc-btn-primary tc-btn-ghost text-sm inline-flex items-center">Annuler</a>
        </div>
    </form>
</section>

<template id="cp-chapter-template">
    <div class="cp-chapter rounded-xl border border-slate-200 bg-white p-4 space-y-2 shadow-sm">
        <div class="flex flex-wrap gap-2 justify-between items-center">
            <label class="text-[10px] font-black uppercase tracking-wide text-slate-500">Titre du chapitre</label>
            <div class="flex flex-wrap gap-1">
                <button type="button" class="text-[10px] font-bold uppercase tracking-wide text-slate-600 border border-slate-200 rounded-lg px-2 py-1 bg-white hover:bg-slate-50" data-cp-chapter-up>Monter</button>
                <button type="button" class="text-[10px] font-bold uppercase tracking-wide text-slate-600 border border-slate-200 rounded-lg px-2 py-1 bg-white hover:bg-slate-50" data-cp-chapter-down>Descendre</button>
                <button type="button" class="text-[10px] font-bold uppercase tracking-wide text-rose-700 border border-rose-200 rounded-lg px-2 py-1 bg-rose-50 hover:bg-rose-100" data-cp-chapter-remove>Retirer</button>
            </div>
        </div>
        <input type="text" class="cp-chapter-title w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" maxlength="255" value="" placeholder="Titre affiché dans le sommaire">
        <input type="hidden" class="cp-chapter-slug" value="">
        <label class="text-[10px] font-black uppercase tracking-wide text-slate-500">Corps HTML du chapitre</label>
        <textarea class="cp-chapter-html w-full rounded-lg border border-slate-200 bg-slate-50/50 px-3 py-2 text-sm font-mono min-h-[10rem]" rows="10" placeholder="Balises HTML autorisées (titres, listes, tableaux, liens…)"></textarea>
    </div>
</template>
</div>
<script>
(function () {
  var s = document.getElementById('cp-slug');
  var p = document.getElementById('cp-slug-preview');
  if (s && p) {
    s.addEventListener('input', function () { p.textContent = s.value || 'votre-id'; });
  }
})();
window.cpDocCssHref = <?= json_encode($docCssPublic, JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="<?= htmlspecialchars(url('assets/js/training_custom_page_handbook.js')) ?>" defer></script>
<script src="<?= htmlspecialchars(url('assets/js/training_custom_page_editor.js')) ?>" defer></script>
