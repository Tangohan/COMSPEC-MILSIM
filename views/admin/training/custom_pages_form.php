<?php
declare(strict_types=1);
$page = isset($customPage) && is_array($customPage) ? $customPage : null;
$isEdit = $page !== null;
$action = $isEdit
    ? training_lms_admin_url('pages-html/' . (int) ($page['id'] ?? 0))
    : training_lms_admin_url('pages-html');
?>
<div id="cp-editor-root">
<section class="tc-panel p-6 md:p-8">
    <p class="tc-kicker">Pilotage des formations</p>
    <div class="flex flex-wrap items-start justify-between gap-4 mb-4">
        <div class="min-w-0">
            <h1 class="tc-hero-title mb-2"><?= $isEdit ? 'Modifier la documentation' : 'Nouvelle documentation' ?></h1>
            <p class="text-sm text-slate-600 max-w-2xl leading-relaxed">
                Rédigez le contenu affiché aux membres, contrôlez la mise en page dans l’aperçu, puis enregistrez. Vous pouvez coller une page déjà prête (titre, styles inclus) ou seulement le corps du texte : le site complète alors automatiquement l’en-tête minimal.
            </p>
        </div>
        <a href="<?= htmlspecialchars(training_lms_admin_url('pages-html')) ?>" class="shrink-0 text-sm font-semibold text-emerald-700 hover:underline">← Retour à la liste</a>
    </div>
</section>

<section class="rounded-2xl border border-slate-200 bg-white p-6 md:p-8" aria-labelledby="cp-form-heading">
    <h2 id="cp-form-heading" class="text-sm font-black uppercase tracking-[0.2em] text-slate-600 mb-1">Contenu de la page</h2>
    <p class="text-xs text-slate-500 mb-6 max-w-3xl leading-relaxed">
        L’aperçu se met à jour pendant la saisie. Le mode « feuillet » sert uniquement à visualiser sur cet écran : il ne modifie pas ce qui est enregistré (sauf si vous copiez le rendu dans le champ vous-même).
    </p>

    <form method="post" action="<?= htmlspecialchars($action) ?>" class="space-y-8">
        <?= \App\Core\Csrf::field() ?>

        <div class="grid gap-8 lg:grid-cols-12">
            <div class="lg:col-span-4 space-y-5">
                <div class="rounded-xl border border-slate-100 bg-slate-50/80 p-5">
                    <h3 class="text-xs font-black uppercase tracking-wide text-slate-700 mb-4">Réglages</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wide text-slate-600 mb-1" for="cp-title">Titre affiché sur le site</label>
                            <input type="text" name="title" id="cp-title" required maxlength="255" value="<?= htmlspecialchars((string) ($page['title'] ?? '')) ?>" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="ex. Livret sécurité routière">
                            <p class="text-[11px] text-slate-500 mt-1">Utilisé pour l’onglet du navigateur et comme titre par défaut si vous ne collez qu’un extrait de page.</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wide text-slate-600 mb-1" for="cp-slug">Adresse courte de la page</label>
                            <input type="text" name="slug" id="cp-slug" required maxlength="120" pattern="[a-z0-9-]+" value="<?= htmlspecialchars((string) ($page['slug'] ?? '')) ?>" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-mono" placeholder="ex. livret-securite-routiere">
                            <p class="text-[11px] text-slate-500 mt-1.5 leading-relaxed">Lettres minuscules, chiffres et tirets uniquement. Lien pour les membres : <code class="bg-white border border-slate-200 px-1 rounded text-[11px]">/formations/page/<span id="cp-slug-preview"><?= htmlspecialchars((string) ($page['slug'] ?? 'votre-id')) ?></span></code></p>
                        </div>
                        <div class="flex items-start gap-3 rounded-lg border border-emerald-100 bg-emerald-50/50 px-3 py-3">
                            <input type="checkbox" name="is_published" id="cp-pub" value="1" class="mt-0.5 rounded border-slate-300 text-emerald-600" <?= !empty($page['is_published']) ? ' checked' : '' ?>>
                            <div>
                                <label for="cp-pub" class="text-sm font-semibold text-slate-900 cursor-pointer">Publier</label>
                                <p class="text-[11px] text-slate-600 mt-0.5 leading-relaxed">Les membres connectés peuvent ouvrir la page. Sinon elle reste en brouillon.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-8 space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <span id="cp-detect-badge" class="inline-flex items-center rounded-full border border-slate-200 bg-slate-100 px-3 py-1 text-[11px] font-bold uppercase tracking-wide text-slate-700">…</span>
                    </div>
                    <label class="inline-flex items-center gap-2 cursor-pointer select-none text-sm text-slate-700">
                        <input type="checkbox" id="cp-livret-preview" class="rounded border-slate-300 text-emerald-600">
                        <span class="font-medium">Aperçu façon feuillet</span>
                    </label>
                </div>
                <p class="text-[11px] text-slate-500 leading-relaxed">Cochez « feuillet » pour voir bandeau sombre, feuille blanche centrée et filigrané « Aperçu » — utile pour les livrets ; cela ne change pas le texte enregistré.</p>

                <div id="cp-mobile-tablist" class="flex gap-2 md:hidden mb-1" role="tablist" aria-label="Mode d’affichage de l’éditeur">
                    <button type="button" class="flex-1 rounded-xl border border-slate-200 px-3 py-2.5 text-xs font-black uppercase tracking-wide transition-colors bg-white shadow-sm text-slate-900" data-cp-tab="code" role="tab" aria-selected="true">Code source</button>
                    <button type="button" class="flex-1 rounded-xl border border-slate-200 px-3 py-2.5 text-xs font-black uppercase tracking-wide transition-colors text-slate-500" data-cp-tab="preview" role="tab" aria-selected="false">Aperçu</button>
                </div>

                <div class="grid gap-4 md:grid-cols-2 md:gap-5">
                    <div id="cp-panel-code" class="min-w-0 space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <label class="text-xs font-black uppercase tracking-wide text-slate-700" for="cp-html">Code source</label>
                        </div>
                        <textarea name="html_body" id="cp-html" required rows="20" class="w-full min-h-[22rem] md:min-h-[32rem] rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-3 text-sm font-mono leading-relaxed text-slate-900 shadow-inner focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-400"><?= htmlspecialchars((string) ($page['html_body'] ?? '')) ?></textarea>
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
                        <p class="text-[11px] text-slate-500 leading-relaxed">L’aperçu peut différer légèrement du rendu final (polices, marges). Ne collez que du contenu de confiance : le code est enregistré tel quel.</p>
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
</div>
<script>
(function () {
  var s = document.getElementById('cp-slug');
  var p = document.getElementById('cp-slug-preview');
  if (s && p) {
    s.addEventListener('input', function () { p.textContent = s.value || 'votre-id'; });
  }
})();
</script>
<script src="<?= htmlspecialchars(url('assets/js/training_custom_page_editor.js')) ?>" defer></script>
