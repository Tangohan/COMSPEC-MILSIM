<?php
declare(strict_types=1);
$group = $group ?? null;
$definition = $definition ?? [];
$units_flat = $units_flat ?? [];
$org_roles = $org_roles ?? [];
$members = $members ?? [];
$isEdit = $group !== null;
$action = $isEdit
    ? url('back-office/communications/groups/' . (int) ($group['id'] ?? 0) . '/update')
    : url('back-office/communications/groups/store');

$dAll = !empty($definition['all_members']);
$dUnits = is_array($definition['unit_ids'] ?? null) ? array_map('intval', $definition['unit_ids']) : [];
$dInc = array_key_exists('include_descendants', $definition) ? (bool) $definition['include_descendants'] : true;
$dRoles = is_array($definition['role_slugs'] ?? null) ? $definition['role_slugs'] : [];
$dExtra = is_array($definition['extra_user_ids'] ?? null) ? array_map('intval', $definition['extra_user_ids']) : [];
?>
<div class="max-w-3xl mx-auto px-6 py-10">
    <h1 class="text-2xl font-black text-slate-900 mb-6"><?= $isEdit ? 'Modifier le groupe' : 'Nouveau groupe' ?></h1>
    <p class="text-sm mb-6"><a href="<?= url('back-office/communications/groups') ?>" class="text-blue-700 font-semibold hover:underline">← Groupes</a></p>

    <form method="post" action="<?= htmlspecialchars($action) ?>" class="space-y-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <?= \App\Core\Csrf::field() ?>
        <div>
            <label for="name" class="block text-sm font-semibold text-slate-800 mb-1">Nom du groupe</label>
            <input type="text" name="name" id="name" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" value="<?= htmlspecialchars((string) ($group['name'] ?? '')) ?>">
        </div>
        <div>
            <label for="description" class="block text-sm font-semibold text-slate-800 mb-1">Description (optionnel)</label>
            <input type="text" name="description" id="description" maxlength="500" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" value="<?= htmlspecialchars((string) ($group['description'] ?? '')) ?>">
        </div>

        <div class="flex items-start gap-3 rounded-lg border border-slate-100 bg-slate-50/80 px-3 py-2">
            <input type="checkbox" name="all_members" id="all_members" value="1" class="mt-1 h-4 w-4" <?= $dAll ? 'checked' : '' ?>>
            <label for="all_members" class="text-sm font-semibold text-slate-900">Inclure tous les membres actifs avec une adresse valide</label>
        </div>

        <div>
            <label class="block text-sm font-semibold text-slate-800 mb-1">Unités</label>
            <select name="unit_ids[]" multiple size="6" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <?php foreach ($units_flat as $u): ?>
                    <?php $sel = in_array((int) ($u['id'] ?? 0), $dUnits, true) ? 'selected' : ''; ?>
                    <option value="<?= (int) ($u['id'] ?? 0) ?>" <?= $sel ?>><?= htmlspecialchars($u['name'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
            <div class="mt-2 flex items-start gap-3">
                <input type="checkbox" name="include_descendants" id="include_descendants" value="1" class="mt-1 h-4 w-4" <?= $dInc ? 'checked' : '' ?>>
                <label for="include_descendants" class="text-sm text-slate-700">Inclure les sous-unités</label>
            </div>
        </div>

        <div>
            <label class="block text-sm font-semibold text-slate-800 mb-1">Rôles communautaires</label>
            <select name="role_slugs[]" multiple size="5" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <?php foreach ($org_roles as $r): ?>
                    <?php $slug = (string) ($r['slug'] ?? ''); ?>
                    <option value="<?= htmlspecialchars($slug) ?>" <?= in_array($slug, $dRoles, true) ? 'selected' : '' ?>><?= htmlspecialchars((string) ($r['name'] ?? $slug)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-sm font-semibold text-slate-800 mb-1">Membres nommés</label>
            <select name="extra_user_ids[]" multiple size="5" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <?php foreach ($members as $m): ?>
                    <?php $mid = (int) ($m['id'] ?? 0); ?>
                    <option value="<?= $mid ?>" <?= in_array($mid, $dExtra, true) ? 'selected' : '' ?>><?= htmlspecialchars(trim(($m['display_name'] ?? '') ?: ($m['email'] ?? ''))) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="rounded-xl bg-slate-900 px-6 py-3 text-sm font-bold text-white">Enregistrer</button>
    </form>
</div>
