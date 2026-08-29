<?php
declare(strict_types=1);

$settings = is_array($duplicateSettings ?? null) ? $duplicateSettings : [];
$labels = is_array($duplicateFieldLabels ?? null) ? $duplicateFieldLabels : [];
$scan = is_array($personnelDuplicateScan ?? null) ? $personnelDuplicateScan : [];
$enabled = array_key_exists('enabled', $settings) ? !empty($settings['enabled']) : true;
$selected = is_array($settings['fields'] ?? null) ? $settings['fields'] : ['matricule', 'callsign'];
$csrfToken = (string) ($csrfToken ?? '');
$groups = is_array($scan['groups'] ?? null) ? $scan['groups'] : [];
?>
<div class="eff-catalog">
    <div class="eff-catalog__head">
        <div class="min-w-0">
            <p class="eff-kicker">Alerte RH</p>
            <h1 class="eff-title">Doublons de fiches</h1>
            <p class="eff-lead">
                Détecte les valeurs identiques entre dossiers (matricule, indicatif, nom…).
                Choisissez quels champs déclenchent l’alerte pour votre communauté.
            </p>
        </div>
    </div>

    <form method="post" action="<?= htmlspecialchars(url('back-office/ressources/effectifs/doublons'), ENT_QUOTES, 'UTF-8') ?>" class="mb-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <label class="flex items-center gap-2 text-sm font-semibold text-slate-800 mb-4">
            <input type="checkbox" name="enabled" value="1" <?= $enabled ? 'checked' : '' ?>>
            Activer la détection de doublons
        </label>
        <p class="text-xs font-bold uppercase tracking-wide text-slate-500 mb-3">Champs considérés comme doublon</p>
        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3 mb-5">
            <?php foreach ($labels as $key => $label): ?>
            <label class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-800">
                <input type="checkbox" name="fields[]" value="<?= htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8') ?>"
                    <?= in_array((string) $key, $selected, true) ? 'checked' : '' ?>>
                <?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?>
            </label>
            <?php endforeach; ?>
        </div>
        <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Enregistrer</button>
    </form>

    <?php if (!$enabled): ?>
    <p class="text-sm text-slate-500">La détection est désactivée. Aucune alerte n’est affichée sur le tableur.</p>
    <?php elseif ($groups === []): ?>
    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
        Aucun doublon détecté sur les champs sélectionnés.
    </div>
    <?php else: ?>
    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 mb-4">
        <strong><?= (int) ($scan['group_count'] ?? 0) ?> groupe(s)</strong>
        · <?= (int) ($scan['member_count'] ?? 0) ?> membre(s) concernés
    </div>
    <ul class="space-y-3">
        <?php foreach ($groups as $g): ?>
        <li class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                <?= htmlspecialchars((string) ($g['field_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                · « <?= htmlspecialchars((string) ($g['value'] ?? ''), ENT_QUOTES, 'UTF-8') ?> »
            </p>
            <ul class="mt-2 flex flex-wrap gap-2">
                <?php foreach (($g['members'] ?? []) as $m): ?>
                <li>
                    <a class="inline-flex rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-sm font-semibold text-slate-800 hover:border-slate-400"
                       href="<?= htmlspecialchars(url('personnel/' . (int) ($m['id'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars((string) ($m['display_name'] ?? 'Membre'), ENT_QUOTES, 'UTF-8') ?>
                        <?php if (!empty($m['callsign'])): ?>
                        <span class="ml-1 text-slate-500">(<?= htmlspecialchars((string) $m['callsign'], ENT_QUOTES, 'UTF-8') ?>)</span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>
