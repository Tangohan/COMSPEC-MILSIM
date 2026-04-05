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
        ?>
        <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-4 space-y-3" data-milsim-field="<?= htmlspecialchars($fk, ENT_QUOTES, 'UTF-8') ?>">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="text-[10px] font-mono font-bold text-slate-500"><?= htmlspecialchars($fk, ENT_QUOTES, 'UTF-8') ?></span>
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
            <div>
                <label class="block text-[11px] font-bold text-slate-600 mb-1">Options (liste / Oui‑Non) — une valeur par ligne</label>
                <textarea name="<?= $nameBase ?>[options]" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs font-mono" placeholder="Pour « liste » ou pour personnaliser Oui/Non"><?= htmlspecialchars($optLines, ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
        </div>
    <?php endforeach; ?>
</div>
