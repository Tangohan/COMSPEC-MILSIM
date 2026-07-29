<?php
declare(strict_types=1);
/**
 * Sélection des créneaux de disponibilité (catalogue + libellés personnalisés).
 *
 * @var list<array{id: string, label: string}>|null $selectedSlots
 * @var string $idsInputName   ex. em_availability_slot_ids[] ou wizard_milsim[availability_slot_ids][]
 * @var string $customInputName ex. em_availability_slot_custom[] ou wizard_milsim[availability_slot_custom][]
 * @var string|null $configuredFlagName champ caché pour marquer une saisie explicite (wizard)
 * @var string|null $formId attribut form HTML optionnel (modal hors formulaire)
 */
$selectedSlots = is_array($selectedSlots ?? null) ? $selectedSlots : [];
$idsInputName = isset($idsInputName) ? (string) $idsInputName : 'em_availability_slot_ids[]';
$customInputName = isset($customInputName) ? (string) $customInputName : 'em_availability_slot_custom[]';
$configuredFlagName = isset($configuredFlagName) ? (string) $configuredFlagName : '';
$formId = isset($formId) ? (string) $formId : '';
$formAttr = $formId !== '' ? ' form="' . htmlspecialchars($formId, ENT_QUOTES, 'UTF-8') . '"' : '';

$suggested = \App\Services\Community\EnlistmentMilsimPackService::suggestedAvailabilitySlots();
$selectedIds = [];
$customLabels = [];
foreach ($selectedSlots as $slot) {
    if (!is_array($slot)) {
        continue;
    }
    $id = (string) ($slot['id'] ?? '');
    $label = (string) ($slot['label'] ?? '');
    if ($id !== '' && isset($suggested[$id])) {
        $selectedIds[$id] = true;
        continue;
    }
    if ($label !== '') {
        $customLabels[] = $label;
    }
}
?>
<div class="space-y-4" data-availability-slots-editor>
    <?php if ($configuredFlagName !== ''): ?>
        <input type="hidden" name="<?= htmlspecialchars($configuredFlagName, ENT_QUOTES, 'UTF-8') ?>" value="1"<?= $formAttr ?>>
    <?php endif; ?>
    <div>
        <p class="text-xs font-bold text-slate-800">Créneaux proposés aux candidats</p>
        <p class="mt-1 text-xs leading-relaxed text-slate-600">Cochez les créneaux que votre communauté attend. Les candidats les verront sous forme de cases à cocher sur le dossier de candidature.</p>
    </div>
    <div class="grid gap-2 sm:grid-cols-2">
        <?php foreach ($suggested as $sid => $slab): ?>
            <label class="flex items-start gap-2.5 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 hover:border-emerald-300 hover:bg-emerald-50/40">
                <input type="checkbox"
                       name="<?= htmlspecialchars($idsInputName, ENT_QUOTES, 'UTF-8') ?>"
                       value="<?= htmlspecialchars($sid, ENT_QUOTES, 'UTF-8') ?>"
                       class="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-600"
                       <?= isset($selectedIds[$sid]) ? 'checked' : '' ?><?= $formAttr ?>>
                <span><?= htmlspecialchars($slab, ENT_QUOTES, 'UTF-8') ?></span>
            </label>
        <?php endforeach; ?>
    </div>
    <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50/80 p-4 space-y-3">
        <p class="text-xs font-bold text-slate-700">Créneaux supplémentaires</p>
        <p class="text-[11px] text-slate-500">Ajoutez un libellé clair (ex. « Mardi soir — entraînement »). Laissez vide si inutile.</p>
        <div class="space-y-2" data-availability-custom-rows>
            <?php
            $customRows = $customLabels !== [] ? $customLabels : [''];
            foreach ($customRows as $i => $clab):
            ?>
            <input type="text"
                   name="<?= htmlspecialchars($customInputName, ENT_QUOTES, 'UTF-8') ?>"
                   value="<?= htmlspecialchars((string) $clab, ENT_QUOTES, 'UTF-8') ?>"
                   maxlength="80"
                   class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
                   placeholder="Libellé du créneau (optionnel)"<?= $formAttr ?>>
            <?php endforeach; ?>
        </div>
        <button type="button"
                class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-[11px] font-bold text-slate-700 hover:bg-slate-100"
                data-availability-add-custom>
            Ajouter un créneau
        </button>
    </div>
    <p class="text-[11px] text-slate-500">Si aucun créneau n’est coché, le candidat pourra décrire sa disponibilité en texte libre.</p>
</div>
<script>
(function () {
    if (window.__availabilitySlotsEditorBound) return;
    window.__availabilitySlotsEditorBound = true;
    document.addEventListener('click', function (ev) {
        var btn = ev.target && ev.target.closest ? ev.target.closest('[data-availability-add-custom]') : null;
        if (!btn) return;
        var root = btn.closest('[data-availability-slots-editor]');
        if (!root) return;
        var box = root.querySelector('[data-availability-custom-rows]');
        if (!box) return;
        var first = box.querySelector('input');
        if (!first) return;
        var clone = first.cloneNode(true);
        clone.value = '';
        box.appendChild(clone);
        clone.focus();
    });
})();
</script>
