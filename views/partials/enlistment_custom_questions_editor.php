<?php
declare(strict_types=1);
/**
 * Éditeur : questions supplémentaires du dossier candidature (listes déroulantes, etc.).
 *
 * @var list<array{id:string,label:string,widget:string,options:list<string>,required:bool,section:string}> $customQuestions
 */
$customQuestions = is_array($customQuestions ?? null) ? $customQuestions : [];
$sectionLabels = [
    'identity' => 'Identité et contact',
    'gear' => 'Matériel et expérience',
    'motivation' => 'Motivation',
    'commitment' => 'Engagement',
];
$widgetLabels = [
    'select' => 'Liste déroulante',
    'yesno' => 'Oui / Non',
    'textarea' => 'Zone de texte',
    'text' => 'Texte court',
];
?>
<div class="space-y-4" id="em-custom-questions-editor" data-em-cq-count="<?= count($customQuestions) ?>">
    <p class="text-xs leading-relaxed text-slate-600">
        Ajoutez des questions propres à votre communauté (par exemple des engagements en liste déroulante).
        Elles s’affichent sur le formulaire public, dans la section choisie.
    </p>

    <div id="em-cq-list" class="space-y-3">
        <?php foreach ($customQuestions as $i => $q): ?>
            <?php
            $qid = (string) ($q['id'] ?? '');
            $widget = (string) ($q['widget'] ?? 'select');
            $section = (string) ($q['section'] ?? 'commitment');
            $optLines = implode("\n", is_array($q['options'] ?? null) ? $q['options'] : []);
            $needsOpts = in_array($widget, ['select', 'yesno'], true);
            ?>
            <article class="rounded-xl border border-slate-200 bg-slate-50/80 p-4 space-y-3" data-em-cq-row>
                <input type="hidden" name="em_custom_questions[<?= (int) $i ?>][id]" value="<?= htmlspecialchars($qid, ENT_QUOTES, 'UTF-8') ?>">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Question supplémentaire</span>
                    <button type="button" class="em-cq-remove text-xs font-semibold text-rose-700 underline decoration-rose-300">Retirer</button>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">Intitulé</label>
                    <input type="text" name="em_custom_questions[<?= (int) $i ?>][label]" value="<?= htmlspecialchars((string) ($q['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" maxlength="240" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" required>
                </div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Type</label>
                        <select name="em_custom_questions[<?= (int) $i ?>][widget]" class="em-cq-widget w-full rounded-lg border border-slate-300 bg-white px-2 py-2 text-sm font-semibold">
                            <?php foreach ($widgetLabels as $wv => $wl): ?>
                                <option value="<?= htmlspecialchars($wv, ENT_QUOTES, 'UTF-8') ?>" <?= $widget === $wv ? 'selected' : '' ?>><?= htmlspecialchars($wl, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Section du formulaire</label>
                        <select name="em_custom_questions[<?= (int) $i ?>][section]" class="w-full rounded-lg border border-slate-300 bg-white px-2 py-2 text-sm">
                            <?php foreach ($sectionLabels as $sv => $sl): ?>
                                <option value="<?= htmlspecialchars($sv, ENT_QUOTES, 'UTF-8') ?>" <?= $section === $sv ? 'selected' : '' ?>><?= htmlspecialchars($sl, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex items-end pb-1">
                        <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-800">
                            <input type="checkbox" name="em_custom_questions[<?= (int) $i ?>][required]" value="1" <?= !empty($q['required']) ? 'checked' : '' ?> class="rounded border-slate-300 text-emerald-700">
                            Réponse obligatoire
                        </label>
                    </div>
                </div>
                <div class="em-cq-options"<?= $needsOpts ? '' : ' hidden' ?>>
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">Choix proposés (une option par ligne)</label>
                    <textarea name="em_custom_questions[<?= (int) $i ?>][options]" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Oui&#10;Non&#10;Variable"><?= htmlspecialchars($optLines, ENT_QUOTES, 'UTF-8') ?></textarea>
                    <p class="mt-1 text-[11px] text-slate-500">Pour une liste déroulante, indiquez au moins deux choix. Pour Oui / Non, laissez vide pour utiliser Oui et Non.</p>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <button type="button" id="em-cq-add" class="inline-flex items-center justify-center rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-2.5 text-sm font-bold text-emerald-950 hover:bg-emerald-100">
        Ajouter une question
    </button>
</div>

<template id="em-cq-row-template">
    <article class="rounded-xl border border-slate-200 bg-slate-50/80 p-4 space-y-3" data-em-cq-row>
        <input type="hidden" data-name="id" value="">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <span class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Question supplémentaire</span>
            <button type="button" class="em-cq-remove text-xs font-semibold text-rose-700 underline decoration-rose-300">Retirer</button>
        </div>
        <div>
            <label class="block text-[11px] font-bold text-slate-600 mb-1">Intitulé</label>
            <input type="text" data-name="label" value="" maxlength="240" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" required>
        </div>
        <div class="grid gap-3 sm:grid-cols-3">
            <div>
                <label class="block text-[11px] font-bold text-slate-600 mb-1">Type</label>
                <select data-name="widget" class="em-cq-widget w-full rounded-lg border border-slate-300 bg-white px-2 py-2 text-sm font-semibold">
                    <option value="select" selected>Liste déroulante</option>
                    <option value="yesno">Oui / Non</option>
                    <option value="textarea">Zone de texte</option>
                    <option value="text">Texte court</option>
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-600 mb-1">Section du formulaire</label>
                <select data-name="section" class="w-full rounded-lg border border-slate-300 bg-white px-2 py-2 text-sm">
                    <option value="commitment" selected>Engagement</option>
                    <option value="motivation">Motivation</option>
                    <option value="gear">Matériel et expérience</option>
                    <option value="identity">Identité et contact</option>
                </select>
            </div>
            <div class="flex items-end pb-1">
                <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-800">
                    <input type="checkbox" data-name="required" value="1" class="rounded border-slate-300 text-emerald-700">
                    Réponse obligatoire
                </label>
            </div>
        </div>
        <div class="em-cq-options">
            <label class="block text-[11px] font-bold text-slate-600 mb-1">Choix proposés (une option par ligne)</label>
            <textarea data-name="options" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Oui&#10;Non"></textarea>
            <p class="mt-1 text-[11px] text-slate-500">Pour une liste déroulante, indiquez au moins deux choix.</p>
        </div>
    </article>
</template>

<script>
(function () {
    var root = document.getElementById('em-custom-questions-editor');
    if (!root) return;
    var list = document.getElementById('em-cq-list');
    var tpl = document.getElementById('em-cq-row-template');
    var addBtn = document.getElementById('em-cq-add');
    var idx = parseInt(root.getAttribute('data-em-cq-count') || '0', 10) || 0;

    function wireRow(row, i) {
        row.querySelectorAll('[data-name]').forEach(function (el) {
            var n = el.getAttribute('data-name');
            el.setAttribute('name', 'em_custom_questions[' + i + '][' + n + ']');
            el.removeAttribute('data-name');
        });
        var idInput = row.querySelector('input[name$="[id]"]');
        if (idInput && !idInput.value) {
            idInput.value = 'cq_' + Math.random().toString(16).slice(2, 10);
        }
        var w = row.querySelector('.em-cq-widget');
        var opts = row.querySelector('.em-cq-options');
        function syncOpts() {
            if (!w || !opts) return;
            var v = w.value;
            opts.hidden = !(v === 'select' || v === 'yesno');
        }
        if (w) w.addEventListener('change', syncOpts);
        syncOpts();
        var rm = row.querySelector('.em-cq-remove');
        if (rm) rm.addEventListener('click', function () { row.remove(); });
    }

    list.querySelectorAll('[data-em-cq-row]').forEach(function (row, i) {
        var w = row.querySelector('.em-cq-widget');
        var opts = row.querySelector('.em-cq-options');
        function syncOpts() {
            if (!w || !opts) return;
            opts.hidden = !(w.value === 'select' || w.value === 'yesno');
        }
        if (w) w.addEventListener('change', syncOpts);
        var rm = row.querySelector('.em-cq-remove');
        if (rm) rm.addEventListener('click', function () { row.remove(); });
    });

    if (addBtn && tpl && list) {
        addBtn.addEventListener('click', function () {
            var node = tpl.content.cloneNode(true);
            var row = node.querySelector('[data-em-cq-row]');
            if (!row) return;
            list.appendChild(node);
            var appended = list.lastElementChild;
            wireRow(appended, idx++);
        });
    }
})();
</script>
