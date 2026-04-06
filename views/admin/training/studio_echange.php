<?php
declare(strict_types=1);

$echangeCourse = $echangeCourse ?? null;
$echangeJsonPretty = (string) ($echangeJsonPretty ?? '');
$echangeCanReplace = (bool) ($echangeCanReplace ?? false);
$echangeReplaceCourseId = isset($echangeReplaceCourseId) ? (int) $echangeReplaceCourseId : null;
$importActionUrl = url(training_studio_path() . '/echange/import');
?>
<div>
    <?php
    $flashOk = \App\Core\Session::getFlash('success');
    $flashErr = \App\Core\Session::getFlash('error');
    ?>
    <?php if ($flashOk): ?>
    <div class="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200/80 text-emerald-950 text-sm font-medium shadow-sm"><?= htmlspecialchars((string) $flashOk) ?></div>
    <?php endif; ?>
    <?php if ($flashErr): ?>
    <div class="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200/80 text-rose-950 text-sm font-medium shadow-sm"><?= htmlspecialchars((string) $flashErr) ?></div>
    <?php endif; ?>

    <header class="training-studio-hero mb-8">
        <p class="text-[0.65rem] font-black tracking-[0.35em] uppercase text-emerald-600 mb-3">Studio formation</p>
        <h1 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight uppercase leading-tight">
            <?= $echangeCourse ? 'Exporter ou importer cette formation' : 'Importer une formation' ?>
        </h1>
        <p class="text-slate-600 text-sm mt-3 max-w-3xl leading-relaxed">
            Consultez ou téléchargez la description complète du parcours (textes, modules, leçons, questionnaires).
            Vous pouvez la réutiliser pour dupliquer une formation, la modifier dans un outil d’assistance, ou la réinjecter ici.
            Pour le format page web : utilisez des titres <code class="text-xs bg-slate-100 px-1 rounded">&lt;h1&gt;</code> pour chaque module
            et des <code class="text-xs bg-slate-100 px-1 rounded">&lt;h2&gt;</code> pour chaque leçon.
        </p>
        <p class="text-sm text-slate-500 mt-2">
            <a href="<?= training_studio_url() ?>" class="font-semibold text-slate-700 underline decoration-slate-300 hover:text-emerald-800">← Tableau des formations</a>
            <?php if ($echangeCourse): ?>
            <span class="text-slate-300 mx-2">·</span>
            <a href="<?= htmlspecialchars(training_studio_url((int) ($echangeCourse['id'] ?? 0) . '/fiche')) ?>" class="text-slate-600 underline decoration-slate-200 hover:text-emerald-800">Fiche de la formation</a>
            <?php endif; ?>
        </p>
    </header>

    <?php if ($echangeJsonPretty !== ''): ?>
    <section class="training-studio-panel mb-8 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/80 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-500">Document complet</h2>
                <p class="text-sm text-slate-600 mt-0.5">Lecture seule — copiez ou enregistrez le fichier ci-dessous.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" id="studio-echange-copy"
                        class="inline-flex items-center justify-center px-4 py-2 rounded-lg text-sm font-bold bg-slate-900 text-white hover:bg-slate-800 shadow-sm">
                    Copier dans le presse-papiers
                </button>
                <a href="<?= htmlspecialchars(training_studio_url((int) ($echangeCourse['id'] ?? 0) . '/echange/export')) ?>"
                   class="inline-flex items-center justify-center px-4 py-2 rounded-lg text-sm font-bold bg-emerald-600 text-white hover:bg-emerald-500 shadow-sm">
                    Télécharger le fichier
                </a>
            </div>
        </div>
        <div class="p-0">
            <label for="studio-echange-json" class="sr-only">Contenu exporté</label>
            <textarea id="studio-echange-json" readonly rows="22"
                      class="w-full font-mono text-xs leading-relaxed p-4 border-0 bg-slate-900 text-emerald-100 focus:ring-0 resize-y min-h-[280px]"><?= htmlspecialchars($echangeJsonPretty) ?></textarea>
        </div>
    </section>
    <script>
    (function () {
        var btn = document.getElementById('studio-echange-copy');
        var ta = document.getElementById('studio-echange-json');
        if (!btn || !ta) return;
        btn.addEventListener('click', function () {
            ta.select();
            ta.setSelectionRange(0, ta.value.length);
            try {
                navigator.clipboard.writeText(ta.value);
                btn.textContent = 'Copié';
                setTimeout(function () { btn.textContent = 'Copier dans le presse-papiers'; }, 2000);
            } catch (e) {
                document.execCommand('copy');
                btn.textContent = 'Copié';
                setTimeout(function () { btn.textContent = 'Copier dans le presse-papiers'; }, 2000);
            }
        });
    })();
    </script>
    <?php endif; ?>

    <section class="training-studio-panel overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/80">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-500">Importer</h2>
            <p class="text-sm text-slate-600 mt-0.5">Collez un document exporté depuis ce studio, ou une page HTML structurée (titres de sections).</p>
        </div>
        <form method="post" action="<?= htmlspecialchars($importActionUrl) ?>" enctype="multipart/form-data" class="p-5 space-y-6">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="_redirect_from" value="<?= $echangeCourse ? 'exchange' : 'importer' ?>">
            <?php if ($echangeReplaceCourseId !== null && $echangeReplaceCourseId > 0): ?>
            <input type="hidden" name="replace_course_id" value="<?= (int) $echangeReplaceCourseId ?>">
            <?php endif; ?>

            <fieldset class="space-y-3">
                <legend class="text-sm font-bold text-slate-800">Format du contenu</legend>
                <label class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                    <input type="radio" name="exchange_format" value="json" class="rounded-full border-slate-300 text-emerald-600 focus:ring-emerald-500" checked>
                    Document structuré (export du Studio)
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                    <input type="radio" name="exchange_format" value="html" class="rounded-full border-slate-300 text-emerald-600 focus:ring-emerald-500">
                    Page HTML (titres de sections)
                </label>
            </fieldset>

            <?php if ($echangeCanReplace && $echangeReplaceCourseId !== null && $echangeReplaceCourseId > 0): ?>
            <fieldset class="space-y-3 rounded-xl border border-amber-200/80 bg-amber-50/50 p-4">
                <legend class="text-sm font-bold text-amber-950">Cible de l’import</legend>
                <label class="flex items-center gap-2 text-sm text-slate-800 cursor-pointer">
                    <input type="radio" name="import_mode" value="new" class="border-slate-300 text-emerald-600 focus:ring-emerald-500" checked>
                    Créer une <strong>nouvelle</strong> formation (recommandé)
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-800 cursor-pointer">
                    <input type="radio" name="import_mode" value="replace" class="border-slate-300 text-emerald-600 focus:ring-emerald-500">
                    Remplacer modules et leçons de <strong>cette</strong> formation
                </label>
                <p class="text-xs text-amber-950/90 leading-relaxed">
                    Le remplacement supprime tous les modules actuels, les leçons et les questionnaires liés, puis recrée la structure à partir du document.
                    Les inscriptions existantes peuvent être impactées (progression et certificats).
                </p>
                <label class="flex items-start gap-2 text-sm text-slate-800 cursor-pointer">
                    <input type="checkbox" name="confirm_replace_structure" value="1" class="mt-1 border-slate-300 text-emerald-600 focus:ring-emerald-500">
                    Je confirme vouloir effacer la structure actuelle avant import.
                </label>
            </fieldset>
            <?php else: ?>
            <input type="hidden" name="import_mode" value="new">
            <?php endif; ?>

            <div class="space-y-2" id="studio-echange-html-title" hidden>
                <label for="html_course_title" class="text-sm font-bold text-slate-800">Titre de la nouvelle formation</label>
                <input type="text" name="html_course_title" id="html_course_title"
                       class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm shadow-sm focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-400"
                       placeholder="Ex. Introduction au tir de précision">
            </div>

            <div class="space-y-2">
                <label for="exchange_payload" class="text-sm font-bold text-slate-800">Contenu à importer</label>
                <textarea name="exchange_payload" id="exchange_payload" rows="14"
                          class="w-full font-mono text-xs border border-slate-200 rounded-lg px-3 py-2 shadow-sm focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-400"
                          placeholder="Collez ici le document ou le HTML…"></textarea>
            </div>

            <div class="space-y-2">
                <label for="exchange_file" class="text-sm font-bold text-slate-800">Ou choisir un fichier sur votre appareil</label>
                <input type="file" name="exchange_file" id="exchange_file" accept=".json,.html,.htm,text/plain,application/json"
                       class="block w-full text-sm text-slate-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-emerald-50 file:text-emerald-800 hover:file:bg-emerald-100">
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <button type="submit" class="inline-flex items-center justify-center px-5 py-2.5 rounded-lg text-sm font-black uppercase tracking-wide bg-emerald-600 text-white hover:bg-emerald-500 shadow-md">
                    Lancer l’import
                </button>
                <?php if (!$echangeCourse): ?>
                <a href="<?= training_studio_url() ?>" class="inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-600 border border-slate-200 bg-white hover:bg-slate-50">Annuler</a>
                <?php endif; ?>
            </div>
        </form>
    </section>
    <script>
    (function () {
        var htmlField = document.getElementById('studio-echange-html-title');
        var radios = document.querySelectorAll('input[name="exchange_format"]');
        function sync() {
            var v = 'json';
            radios.forEach(function (r) { if (r.checked) v = r.value; });
            if (htmlField) htmlField.hidden = v !== 'html';
        }
        radios.forEach(function (r) { r.addEventListener('change', sync); });
        sync();
    })();
    </script>
</div>
