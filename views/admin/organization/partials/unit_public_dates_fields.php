<?php
declare(strict_types=1);

/**
 * Champs dates publiques d’une unité (création + date complémentaire).
 *
 * @var array<string, mixed> $unitRow
 * @var string $idPrefix préfixe d’id HTML (ex. group, team)
 */

$unitRow = is_array($unitRow ?? null) ? $unitRow : [];
$idPrefix = preg_replace('/[^a-z0-9_-]/i', '', (string) ($idPrefix ?? 'unit')) ?: 'unit';

$foundedOn = trim((string) ($unitRow['public_founded_on'] ?? ''));
if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $foundedOn, $m)) {
    $foundedOn = $m[1];
} else {
    $foundedOn = '';
}

$customDate = trim((string) ($unitRow['public_custom_date'] ?? ''));
if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $customDate, $m2)) {
    $customDate = $m2[1];
} else {
    $customDate = '';
}

$customLabel = trim((string) ($unitRow['public_custom_date_label'] ?? ''));

$labelPresets = [
    '' => '— Choisir un libellé —',
    'Mise en service' => 'Mise en service',
    'Activation' => 'Activation',
    'Réorganisation' => 'Réorganisation',
    'Anniversaire' => 'Anniversaire',
];
$labelIsCustom = $customLabel !== '' && !array_key_exists($customLabel, $labelPresets);
?>
<div class="space-y-3 rounded-xl border border-slate-200 bg-slate-50/80 p-4">
    <div>
        <p class="text-xs font-bold text-slate-800">Dates sur la fiche publique</p>
        <p class="mt-0.5 text-xs text-slate-500">Ces dates apparaissent sur la page publique de l’unité. Laissez vide pour ne rien afficher.</p>
    </div>
    <div>
        <label for="<?= htmlspecialchars($idPrefix, ENT_QUOTES, 'UTF-8') ?>-public-founded-on" class="block text-sm font-medium text-slate-700">Date de création</label>
        <input
            type="date"
            id="<?= htmlspecialchars($idPrefix, ENT_QUOTES, 'UTF-8') ?>-public-founded-on"
            name="public_founded_on"
            value="<?= htmlspecialchars($foundedOn, ENT_QUOTES, 'UTF-8') ?>"
            class="mt-1 block w-full max-w-xs rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm"
        >
    </div>
    <div class="grid gap-3 sm:grid-cols-2">
        <div>
            <label for="<?= htmlspecialchars($idPrefix, ENT_QUOTES, 'UTF-8') ?>-public-custom-date-label-preset" class="block text-sm font-medium text-slate-700">Libellé de la date complémentaire</label>
            <select
                id="<?= htmlspecialchars($idPrefix, ENT_QUOTES, 'UTF-8') ?>-public-custom-date-label-preset"
                class="<?= htmlspecialchars(bo_select_class('mt-1'), ENT_QUOTES, 'UTF-8') ?>"
                data-unit-custom-date-label-preset
            >
                <?php foreach ($labelPresets as $value => $label): ?>
                <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= (!$labelIsCustom && $customLabel === $value) ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
                <option value="__other__" <?= $labelIsCustom ? 'selected' : '' ?>>Autre (saisie libre)</option>
            </select>
            <input
                type="text"
                id="<?= htmlspecialchars($idPrefix, ENT_QUOTES, 'UTF-8') ?>-public-custom-date-label"
                name="public_custom_date_label"
                maxlength="80"
                value="<?= htmlspecialchars($customLabel, ENT_QUOTES, 'UTF-8') ?>"
                class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm <?= $labelIsCustom || $customLabel === '' ? '' : 'hidden' ?>"
                data-unit-custom-date-label-input
                placeholder="Ex. Réactivation, Fusion…"
                <?= $labelIsCustom || $customLabel === '' ? '' : 'readonly' ?>
            >
        </div>
        <div>
            <label for="<?= htmlspecialchars($idPrefix, ENT_QUOTES, 'UTF-8') ?>-public-custom-date" class="block text-sm font-medium text-slate-700">Date complémentaire</label>
            <input
                type="date"
                id="<?= htmlspecialchars($idPrefix, ENT_QUOTES, 'UTF-8') ?>-public-custom-date"
                name="public_custom_date"
                value="<?= htmlspecialchars($customDate, ENT_QUOTES, 'UTF-8') ?>"
                class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm"
            >
            <p class="mt-0.5 text-[11px] text-slate-500">Affichée uniquement si une date et un libellé sont renseignés.</p>
        </div>
    </div>
</div>
<script>
(function () {
    var root = document.currentScript ? document.currentScript.previousElementSibling : null;
    if (!root) return;
    var preset = root.querySelector('[data-unit-custom-date-label-preset]');
    var input = root.querySelector('[data-unit-custom-date-label-input]');
    if (!preset || !input) return;
    function sync() {
        var v = preset.value;
        if (v === '__other__') {
            input.classList.remove('hidden');
            input.readOnly = false;
            if (!input.value) input.focus();
            return;
        }
        if (v === '') {
            input.value = '';
            input.classList.add('hidden');
            input.readOnly = true;
            return;
        }
        input.value = v;
        input.classList.add('hidden');
        input.readOnly = true;
    }
    preset.addEventListener('change', sync);
    sync();
})();
</script>
