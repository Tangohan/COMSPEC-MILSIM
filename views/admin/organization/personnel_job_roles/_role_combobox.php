<?php
declare(strict_types=1);
/**
 * Liste d’emplois avec recherche (voir pjr_role_combobox.js).
 * Variables attendues : $pjrComboName, $pjrComboSelectedId, $pjrComboEmptyValue, $pjrComboEmptyLabel, $pjrComboId, $jobRoleOptions
 */
$pjrComboName = (string) ($pjrComboName ?? 'role_id');
$pjrComboSelectedId = (int) ($pjrComboSelectedId ?? 0);
$pjrComboEmptyValue = (string) ($pjrComboEmptyValue ?? '');
$pjrComboEmptyLabel = (string) ($pjrComboEmptyLabel ?? '—');
$pjrComboId = (string) ($pjrComboId ?? 'pjr-combobox');
$jobRoleOptions = $jobRoleOptions ?? [];

$hiddenValue = $pjrComboEmptyValue === '0'
    ? ($pjrComboSelectedId > 0 ? (string) $pjrComboSelectedId : '0')
    : ($pjrComboSelectedId > 0 ? (string) $pjrComboSelectedId : '');

$initialLabel = $pjrComboEmptyLabel;
if ($pjrComboSelectedId > 0) {
    foreach ($jobRoleOptions as $jo) {
        if ((int) ($jo['id'] ?? 0) === $pjrComboSelectedId) {
            $initialLabel = (string) ($jo['label'] ?? '');
            break;
        }
    }
}
?>
<div
    class="pjr-role-combobox w-full min-w-0"
    data-pjr-role-combobox
    data-reset-value="<?= htmlspecialchars($pjrComboEmptyValue, ENT_QUOTES, 'UTF-8') ?>"
    data-reset-label="<?= htmlspecialchars($pjrComboEmptyLabel, ENT_QUOTES, 'UTF-8') ?>"
>
    <input type="hidden" name="<?= htmlspecialchars($pjrComboName, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($hiddenValue, ENT_QUOTES, 'UTF-8') ?>" class="pjr-role-combobox-value">
    <button
        type="button"
        class="pjr-role-combobox-trigger flex w-full items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-left text-xs text-slate-900 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40"
        aria-haspopup="listbox"
        aria-expanded="false"
        id="<?= htmlspecialchars($pjrComboId, ENT_QUOTES, 'UTF-8') ?>-btn"
        aria-labelledby="<?= htmlspecialchars($pjrComboId, ENT_QUOTES, 'UTF-8') ?>-lbl"
    >
        <span id="<?= htmlspecialchars($pjrComboId, ENT_QUOTES, 'UTF-8') ?>-lbl" class="pjr-role-combobox-label min-w-0 flex-1 truncate font-medium"><?= htmlspecialchars($initialLabel, ENT_QUOTES, 'UTF-8') ?></span>
        <svg class="h-4 w-4 shrink-0 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
        </svg>
    </button>
</div>
