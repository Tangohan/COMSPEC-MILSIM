<?php
$role = $role ?? null;
$catOptions = $catOptions ?? [];
$permissions = $permissions ?? [];
$selectedPerm = $selectedPerm ?? [];
$activeTab = $activeTab ?? 'referentiel';
$rid = $role ? (int) $role['id'] : 0;
$byModule = [];
foreach ($permissions as $p) {
    $m = (string) ($p['module'] ?? 'autre');
    if (!isset($byModule[$m])) {
        $byModule[$m] = [];
    }
    $byModule[$m][] = $p;
}
$selectedSet = array_fill_keys($selectedPerm, true);
?>
<div class="mx-auto max-w-4xl px-6 py-12">
    <?php require __DIR__ . '/_nav.php'; ?>
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-black text-slate-900"><?= $rid ? 'Modifier le rôle métier' : 'Nouveau rôle métier' ?></h1>
        <a href="<?= url('back-office/personnel-job-roles') ?>" class="text-sm font-medium text-slate-600 hover:underline">← Liste</a>
    </div>

    <form method="post" action="<?= url('back-office/personnel-job-roles/roles/save') ?>" class="space-y-8">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="id" value="<?= $rid ?>">

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-700">Informations</h2>
            <div class="grid gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Catégorie</label>
                    <select name="category_id" required class="w-full rounded border border-slate-200 px-3 py-2 text-sm">
                        <?php foreach ($catOptions as $co): ?>
                        <option value="<?= (int) $co['id'] ?>" <?= $role && (int) ($role['category_id'] ?? 0) === (int) $co['id'] ? 'selected' : '' ?>><?= htmlspecialchars($co['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Nom affiché</label>
                    <input type="text" name="name" required value="<?= htmlspecialchars((string) ($role['name'] ?? '')) ?>" class="w-full rounded border border-slate-200 px-3 py-2 text-sm" maxlength="120">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Référence courte du poste (unique)</label>
                    <input type="text" name="slug" required value="<?= htmlspecialchars((string) ($role['slug'] ?? '')) ?>" class="w-full rounded border border-slate-200 px-3 py-2 font-mono text-sm text-slate-900" maxlength="80">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Ordre d’affichage</label>
                    <input type="number" name="sort_order" value="<?= (int) ($role['sort_order'] ?? 0) ?>" class="w-full rounded border border-slate-200 px-3 py-2 text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Description</label>
                    <input type="text" name="description" value="<?= htmlspecialchars((string) ($role['description'] ?? '')) ?>" class="w-full rounded border border-slate-200 px-3 py-2 text-sm" maxlength="500">
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-2 text-sm font-bold uppercase tracking-wide text-slate-700">Permissions (preset)</h2>
            <p class="mb-4 text-xs text-slate-600">Cochez les droits « référent » associés à ce rôle métier (les permissions effectives utilisateur restent celles du rôle communauté + RBAC ; ce lien sert de documentation / futur couplage).</p>
            <?php if (empty($permissions)): ?>
            <p class="text-sm text-amber-800">Aucune permission définie pour ce tenant.</p>
            <?php else: ?>
            <div class="max-h-[480px] space-y-6 overflow-y-auto pr-1">
                <?php foreach ($byModule as $module => $perms): ?>
                <div>
                    <p class="mb-2 text-xs font-black uppercase tracking-wider text-slate-500"><?= htmlspecialchars($module) ?></p>
                    <ul class="grid gap-2 sm:grid-cols-2">
                        <?php foreach ($perms as $p): ?>
                        <?php
                        $pid = (int) $p['id'];
                        $chk = !empty($selectedSet[$pid]);
                        ?>
                        <li class="flex items-start gap-2 rounded border border-slate-100 bg-slate-50/80 px-2 py-1.5">
                            <input type="checkbox" name="permissions[]" value="<?= $pid ?>" id="perm_<?= $pid ?>" class="mt-0.5" <?= $chk ? 'checked' : '' ?>>
                            <label for="perm_<?= $pid ?>" class="cursor-pointer text-xs leading-snug text-slate-800">
                                <span class="font-mono text-[11px] text-slate-600"><?= htmlspecialchars((string) ($p['slug'] ?? '')) ?></span>
                                <?php if (!empty($p['action'])): ?>
                                <span class="text-slate-500"> · <?= htmlspecialchars((string) $p['action']) ?></span>
                                <?php endif; ?>
                                <br><span class="text-slate-700"><?= htmlspecialchars((string) ($p['name'] ?? '')) ?></span>
                            </label>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="rounded-lg bg-slate-900 px-6 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">Enregistrer</button>
            <a href="<?= url('back-office/personnel-job-roles') ?>" class="rounded-lg border border-slate-200 px-6 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Annuler</a>
        </div>
    </form>
</div>
