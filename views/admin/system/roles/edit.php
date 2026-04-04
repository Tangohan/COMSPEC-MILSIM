<?php
$role = $role ?? null;
$permissionIds = $permissionIds ?? [];
$allPermissions = $allPermissions ?? [];
if (!$role) {
    echo '<p>Rôle introuvable.</p>';
    return;
}
$rid = (int) $role['id'];
$permSet = array_flip($permissionIds);
$byModule = [];
foreach ($allPermissions as $p) {
    $m = $p['module'] ?? 'other';
    $byModule[$m][] = $p;
}
?>
<div class="max-w-4xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-2">Modifier les permissions — <?= htmlspecialchars($role['name']) ?></h1>
    <p class="text-slate-600 text-sm mb-6">Cochez les permissions à attribuer à ce rôle.</p>

    <form method="post" action="<?= url('admin/system/roles/' . $rid . '/update') ?>">
        <?= \App\Core\Csrf::field() ?>
        <div class="space-y-6">
            <?php foreach ($byModule as $module => $perms): ?>
            <div class="p-4 bg-slate-50 rounded-lg">
                <h2 class="text-sm font-black text-slate-600 uppercase mb-3"><?= htmlspecialchars($module) ?></h2>
                <ul class="space-y-2">
                    <?php foreach ($perms as $p): ?>
                    <li>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="permission_ids[]" value="<?= (int) $p['id'] ?>" <?= isset($permSet[(int) $p['id']]) ? 'checked' : '' ?>>
                            <span class="font-mono text-sm"><?= htmlspecialchars($p['slug']) ?></span>
                            <span class="text-slate-500 text-sm"><?= htmlspecialchars($p['name']) ?></span>
                        </label>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="mt-6 flex gap-3">
            <button type="submit" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded hover:bg-slate-800">Enregistrer</button>
            <a href="<?= url('admin/system/roles/' . $rid) ?>" class="px-4 py-2 text-slate-600 text-sm hover:underline">Annuler</a>
        </div>
    </form>
</div>
