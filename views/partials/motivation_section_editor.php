<?php
declare(strict_types=1);
/**
 * Éditeur de la section Motivation du dossier de candidature (libellés humains, pas de jargon).
 *
 * @var array<string, mixed> $motivationData
 * @var string $inputPrefix ex. em_motivation ou wizard_milsim[motivation]
 * @var string|null $formAttr attribut form="…" si champs hors formulaire (wizard modal)
 */
$motivationData = is_array($motivationData ?? null)
    ? $motivationData
    : \App\Services\Community\EnlistmentMilsimPackService::defaultMotivationSection();
$motivationData = \App\Services\Community\EnlistmentMilsimPackService::normalizeMotivationSection($motivationData);
$inputPrefix = isset($inputPrefix) ? (string) $inputPrefix : 'em_motivation';
$formAttr = isset($formAttr) && is_string($formAttr) && $formAttr !== ''
    ? ' form="' . htmlspecialchars($formAttr, ENT_QUOTES, 'UTF-8') . '"'
    : '';

$why = $motivationData['why_join'];
$acc = $motivationData['accountability'];
$name = static function (string $suffix) use ($inputPrefix): string {
    return $inputPrefix . $suffix;
};
?>
<div class="space-y-5" data-motivation-section-editor>
    <div class="grid gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <label class="block text-[11px] font-bold text-slate-700 mb-1">Titre de la section</label>
            <input type="text" name="<?= htmlspecialchars($name('[title]'), ENT_QUOTES, 'UTF-8') ?>"<?= $formAttr ?>
                   value="<?= htmlspecialchars((string) $motivationData['title'], ENT_QUOTES, 'UTF-8') ?>"
                   maxlength="200" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                   placeholder="Motivation">
        </div>
        <div class="sm:col-span-2">
            <label class="block text-[11px] font-bold text-slate-700 mb-1">Texte d’introduction (optionnel)</label>
            <textarea name="<?= htmlspecialchars($name('[intro]'), ENT_QUOTES, 'UTF-8') ?>"<?= $formAttr ?>
                      rows="2" maxlength="2000"
                      class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                      placeholder="Courte consigne visible au-dessus des questions."><?= htmlspecialchars((string) $motivationData['intro'], ENT_QUOTES, 'UTF-8') ?></textarea>
            <p class="mt-1 text-[11px] text-slate-500">Affiché sous le titre, pour préciser ce que vous attendez des candidats.</p>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-4 space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs font-black uppercase tracking-wider text-slate-800">Question principale</p>
            <input type="hidden" name="<?= htmlspecialchars($name('[why_join][enabled]'), ENT_QUOTES, 'UTF-8') ?>" value="1"<?= $formAttr ?>>
            <span class="text-[11px] font-semibold text-emerald-800">Toujours affichée</span>
        </div>
        <div>
            <label class="block text-[11px] font-bold text-slate-600 mb-1">Intitulé</label>
            <input type="text" name="<?= htmlspecialchars($name('[why_join][label]'), ENT_QUOTES, 'UTF-8') ?>"<?= $formAttr ?>
                   value="<?= htmlspecialchars((string) $why['label'], ENT_QUOTES, 'UTF-8') ?>"
                   maxlength="240" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-[11px] font-bold text-slate-600 mb-1">Exemple dans le champ</label>
            <input type="text" name="<?= htmlspecialchars($name('[why_join][placeholder]'), ENT_QUOTES, 'UTF-8') ?>"<?= $formAttr ?>
                   value="<?= htmlspecialchars((string) $why['placeholder'], ENT_QUOTES, 'UTF-8') ?>"
                   maxlength="400" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-[11px] font-bold text-slate-600 mb-1">Aide sous la question (optionnel)</label>
            <input type="text" name="<?= htmlspecialchars($name('[why_join][help]'), ENT_QUOTES, 'UTF-8') ?>"<?= $formAttr ?>
                   value="<?= htmlspecialchars((string) $why['help'], ENT_QUOTES, 'UTF-8') ?>"
                   maxlength="600" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                   placeholder="Ex. Expliquez en quelques phrases ce qui vous attire dans l’unité.">
        </div>
        <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-700 cursor-pointer">
            <input type="hidden" name="<?= htmlspecialchars($name('[why_join][required]'), ENT_QUOTES, 'UTF-8') ?>" value="0"<?= $formAttr ?>>
            <input type="checkbox" name="<?= htmlspecialchars($name('[why_join][required]'), ENT_QUOTES, 'UTF-8') ?>" value="1"<?= $formAttr ?>
                <?= !empty($why['required']) ? ' checked' : '' ?> class="rounded border-slate-300 text-emerald-600">
            Réponse obligatoire
        </label>
    </div>

    <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-4 space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs font-black uppercase tracking-wider text-slate-800">Question complémentaire</p>
            <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-700 cursor-pointer">
                <input type="hidden" name="<?= htmlspecialchars($name('[accountability][enabled]'), ENT_QUOTES, 'UTF-8') ?>" value="0"<?= $formAttr ?>>
                <input type="checkbox" name="<?= htmlspecialchars($name('[accountability][enabled]'), ENT_QUOTES, 'UTF-8') ?>" value="1"<?= $formAttr ?>
                    <?= !empty($acc['enabled']) ? ' checked' : '' ?> class="rounded border-slate-300 text-emerald-600">
                Afficher cette question
            </label>
        </div>
        <p class="text-[11px] text-slate-500">Souvent réservée au dossier complet (masquée sur une candidature courte ciblée).</p>
        <div>
            <label class="block text-[11px] font-bold text-slate-600 mb-1">Intitulé</label>
            <input type="text" name="<?= htmlspecialchars($name('[accountability][label]'), ENT_QUOTES, 'UTF-8') ?>"<?= $formAttr ?>
                   value="<?= htmlspecialchars((string) $acc['label'], ENT_QUOTES, 'UTF-8') ?>"
                   maxlength="240" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-[11px] font-bold text-slate-600 mb-1">Exemple dans le champ</label>
            <input type="text" name="<?= htmlspecialchars($name('[accountability][placeholder]'), ENT_QUOTES, 'UTF-8') ?>"<?= $formAttr ?>
                   value="<?= htmlspecialchars((string) $acc['placeholder'], ENT_QUOTES, 'UTF-8') ?>"
                   maxlength="400" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-[11px] font-bold text-slate-600 mb-1">Aide sous la question (optionnel)</label>
            <input type="text" name="<?= htmlspecialchars($name('[accountability][help]'), ENT_QUOTES, 'UTF-8') ?>"<?= $formAttr ?>
                   value="<?= htmlspecialchars((string) $acc['help'], ENT_QUOTES, 'UTF-8') ?>"
                   maxlength="600" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-700 cursor-pointer">
            <input type="hidden" name="<?= htmlspecialchars($name('[accountability][required]'), ENT_QUOTES, 'UTF-8') ?>" value="0"<?= $formAttr ?>>
            <input type="checkbox" name="<?= htmlspecialchars($name('[accountability][required]'), ENT_QUOTES, 'UTF-8') ?>" value="1"<?= $formAttr ?>
                <?= !empty($acc['required']) ? ' checked' : '' ?> class="rounded border-slate-300 text-emerald-600">
            Réponse obligatoire (dossier complet)
        </label>
    </div>
</div>
