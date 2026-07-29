<?php
declare(strict_types=1);
/**
 * Éditeur visuel des champs du dossier MilSim (libellé, aide, type, options liste).
 *
 * @var array<string, array{label: string, placeholder: string, widget: string, options: list<string>}> $fieldsData
 * @var string $inputPrefix nom du tableau POST, ex. em_fld ou wizard_milsim[fields]
 */
$fieldsData = $fieldsData ?? [];
$inputPrefix = isset($inputPrefix) ? (string) $inputPrefix : 'em_fld';
$keys = \App\Services\Community\EnlistmentMilsimPackService::milsimFieldKeys();
// Les questions Motivation ont un éditeur dédié (titre, intro, obligatoire).
$keys = array_values(array_filter($keys, static fn (string $k): bool => !str_starts_with($k, 'motivation_')));
$fieldAdminTitles = [
    'full_name' => 'Nom et prénom (dossier)',
    'legal_full_name' => 'Nom réel pour le contact',
    'age' => 'Âge',
    'timezone' => 'Fuseau horaire',
    'weekly_availability' => 'Disponibilités dans la semaine',
    'email' => 'Adresse e-mail',
    'callsign' => 'Indicatif',
    'system_config' => 'Niveau du PC',
    'microphone_quality' => 'Qualité du micro',
    'past_milsim_experience' => 'Expériences milsim',
    'ace_acre_level' => 'Niveau ACE / ACRE',
];
?>
<div class="space-y-3">
    <?php foreach ($keys as $fk): ?>
        <?php
        $fd = $fieldsData[$fk] ?? \App\Services\Community\EnlistmentMilsimPackService::defaultFieldLabels()[$fk] ?? ['label' => $fk, 'placeholder' => '', 'widget' => 'text', 'options' => []];
        $lab = (string) ($fd['label'] ?? '');
        $ph = (string) ($fd['placeholder'] ?? '');
        $widget = (string) ($fd['widget'] ?? 'text');
        $optLines = is_array($fd['options'] ?? null) ? implode("\n", $fd['options']) : '';
        $nameBase = $inputPrefix . '[' . htmlspecialchars($fk, ENT_QUOTES, 'UTF-8') . ']';
        $adminTitle = $fieldAdminTitles[$fk] ?? ((string) ($fd['label'] ?? 'Champ'));
        ?>
        <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-4 space-y-3" data-milsim-field="<?= htmlspecialchars($fk, ENT_QUOTES, 'UTF-8') ?>">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="text-[11px] font-bold text-slate-700"><?= htmlspecialchars($adminTitle, ENT_QUOTES, 'UTF-8') ?></span>
                <select name="<?= $nameBase ?>[widget]" class="text-xs rounded-lg border border-slate-300 bg-white px-2 py-1 font-semibold text-slate-800">
                    <option value="text" <?= $widget === 'text' ? 'selected' : '' ?>>Texte court</option>
                    <option value="textarea" <?= $widget === 'textarea' ? 'selected' : '' ?>>Zone de texte</option>
                    <option value="select" <?= $widget === 'select' ? 'selected' : '' ?>>Liste déroulante</option>
                    <option value="yesno" <?= $widget === 'yesno' ? 'selected' : '' ?>>Oui / Non</option>
                </select>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">Libellé</label>
                    <input type="text" name="<?= $nameBase ?>[label]" value="<?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?>" maxlength="240" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">Texte d’aide (placeholder)</label>
                    <input type="text" name="<?= $nameBase ?>[placeholder]" value="<?= htmlspecialchars($ph, ENT_QUOTES, 'UTF-8') ?>" maxlength="400" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
            </div>
            <div class="milsim-fld-options" data-widget-for="<?= htmlspecialchars($fk, ENT_QUOTES, 'UTF-8') ?>"<?= in_array($widget, ['select', 'yesno'], true) ? '' : ' hidden' ?>>
                <label class="block text-[11px] font-bold text-slate-600 mb-1">Choix de la liste (une option par ligne)</label>
                <textarea name="<?= $nameBase ?>[options]" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Une option par ligne"><?= htmlspecialchars($optLines, ENT_QUOTES, 'UTF-8') ?></textarea>
                <p class="mt-1 text-[11px] text-slate-500">Utilisé pour « Liste déroulante » et pour personnaliser Oui / Non.</p>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<script>
(function () {
    document.querySelectorAll('[data-milsim-field]').forEach(function (card) {
        var sel = card.querySelector('select[name*="[widget]"]');
        var opts = card.querySelector('.milsim-fld-options');
        if (!sel || !opts) return;
        function sync() {
            var v = sel.value;
            opts.hidden = !(v === 'select' || v === 'yesno');
        }
        sel.addEventListener('change', sync);
        sync();
    });
})();
</script>
