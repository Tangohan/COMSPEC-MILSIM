<?php
declare(strict_types=1);
/**
 * Éditeur des règles de refus automatique (réponse → dossier refusé).
 *
 * @var list<array{field_key:string,match_value:string,candidate_message:string,staff_note:string}> $autoRefuseRules
 * @var array<string, string> $refuseFieldLabels field_key => libellé FR
 */
$autoRefuseRules = is_array($autoRefuseRules ?? null) ? $autoRefuseRules : [];
$refuseFieldLabels = is_array($refuseFieldLabels ?? null) ? $refuseFieldLabels : [];
?>
<div class="space-y-4" id="em-auto-refuse-editor" data-em-ar-count="<?= count($autoRefuseRules) ?>">
    <p class="text-xs leading-relaxed text-slate-600">
        Si le candidat choisit une réponse précise (par exemple « Non » à une question d’engagement),
        le dossier peut être <strong>refusé automatiquement</strong>. Le candidat reçoit un message clair ;
        l’équipe voit la raison dans le dossier.
    </p>

    <div id="em-ar-list" class="space-y-3">
        <?php foreach ($autoRefuseRules as $i => $rule): ?>
            <?php $fk = (string) ($rule['field_key'] ?? ''); ?>
            <article class="rounded-xl border border-rose-200 bg-rose-50/40 p-4 space-y-3" data-em-ar-row>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="text-[11px] font-bold uppercase tracking-wide text-rose-800">Condition de refus</span>
                    <button type="button" class="em-ar-remove text-xs font-semibold text-rose-700 underline decoration-rose-300">Retirer</button>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Question concernée</label>
                        <select name="em_auto_refuse[<?= (int) $i ?>][field_key]" class="w-full rounded-lg border border-slate-300 bg-white px-2 py-2 text-sm">
                            <option value="">— Choisir —</option>
                            <?php foreach ($refuseFieldLabels as $rk => $rl): ?>
                                <option value="<?= htmlspecialchars($rk, ENT_QUOTES, 'UTF-8') ?>" <?= $fk === $rk ? 'selected' : '' ?>><?= htmlspecialchars($rl, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Réponse qui déclenche le refus</label>
                        <input type="text" name="em_auto_refuse[<?= (int) $i ?>][match_value]" value="<?= htmlspecialchars((string) ($rule['match_value'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" maxlength="200" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Ex. Non">
                    </div>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">Message affiché au candidat</label>
                    <textarea name="em_auto_refuse[<?= (int) $i ?>][candidate_message]" rows="2" maxlength="600" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><?= htmlspecialchars((string) ($rule['candidate_message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">Note interne pour l’équipe (non affichée au candidat)</label>
                    <input type="text" name="em_auto_refuse[<?= (int) $i ?>][staff_note]" value="<?= htmlspecialchars((string) ($rule['staff_note'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" maxlength="600" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <button type="button" id="em-ar-add" class="inline-flex items-center justify-center rounded-xl border border-rose-300 bg-white px-4 py-2.5 text-sm font-bold text-rose-950 hover:bg-rose-50">
        Ajouter une condition de refus
    </button>
</div>

<template id="em-ar-row-template">
    <article class="rounded-xl border border-rose-200 bg-rose-50/40 p-4 space-y-3" data-em-ar-row>
        <div class="flex flex-wrap items-center justify-between gap-2">
            <span class="text-[11px] font-bold uppercase tracking-wide text-rose-800">Condition de refus</span>
            <button type="button" class="em-ar-remove text-xs font-semibold text-rose-700 underline decoration-rose-300">Retirer</button>
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <label class="block text-[11px] font-bold text-slate-600 mb-1">Question concernée</label>
                <select data-name="field_key" class="w-full rounded-lg border border-slate-300 bg-white px-2 py-2 text-sm">
                    <option value="">— Choisir —</option>
                    <?php foreach ($refuseFieldLabels as $rk => $rl): ?>
                        <option value="<?= htmlspecialchars($rk, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($rl, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-600 mb-1">Réponse qui déclenche le refus</label>
                <input type="text" data-name="match_value" value="" maxlength="200" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Ex. Non">
            </div>
        </div>
        <div>
            <label class="block text-[11px] font-bold text-slate-600 mb-1">Message affiché au candidat</label>
            <textarea data-name="candidate_message" rows="2" maxlength="600" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Votre candidature ne peut pas être retenue…">Votre candidature ne peut pas être retenue au regard des réponses fournies.</textarea>
        </div>
        <div>
            <label class="block text-[11px] font-bold text-slate-600 mb-1">Note interne pour l’équipe</label>
            <input type="text" data-name="staff_note" value="" maxlength="600" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Refus automatique : …">
        </div>
    </article>
</template>

<script>
(function () {
    var root = document.getElementById('em-auto-refuse-editor');
    if (!root) return;
    var list = document.getElementById('em-ar-list');
    var tpl = document.getElementById('em-ar-row-template');
    var addBtn = document.getElementById('em-ar-add');
    var idx = parseInt(root.getAttribute('data-em-ar-count') || '0', 10) || 0;

    function wireRow(row, i) {
        row.querySelectorAll('[data-name]').forEach(function (el) {
            var n = el.getAttribute('data-name');
            el.setAttribute('name', 'em_auto_refuse[' + i + '][' + n + ']');
            el.removeAttribute('data-name');
        });
        var rm = row.querySelector('.em-ar-remove');
        if (rm) rm.addEventListener('click', function () { row.remove(); });
    }

    list.querySelectorAll('[data-em-ar-row]').forEach(function (row) {
        var rm = row.querySelector('.em-ar-remove');
        if (rm) rm.addEventListener('click', function () { row.remove(); });
    });

    if (addBtn && tpl && list) {
        addBtn.addEventListener('click', function () {
            var node = tpl.content.cloneNode(true);
            list.appendChild(node);
            wireRow(list.lastElementChild, idx++);
        });
    }
})();
</script>
