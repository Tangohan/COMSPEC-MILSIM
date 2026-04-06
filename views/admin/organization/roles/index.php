<?php
$roles = $roles ?? [];
$permissionCounts = $permissionCounts ?? [];
$roleLayerFilter = $roleLayerFilter ?? '';
$base = url('back-office/roles');
$__g = \App\Core\Gate::getInstance();
$canPresets = $__g->allows('admin.organization') || $__g->allows('admin.roles.manage') || $__g->allows('admin.permissions.manage');
?>
<div class="max-w-4xl mx-auto px-6 py-12">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-6">
        <h1 class="text-2xl font-black text-slate-900">Rôles communauté</h1>
        <div class="flex flex-wrap gap-2">
            <?php if ($canPresets): ?>
            <a href="<?= url('back-office/roles/presets') ?>" class="inline-flex items-center rounded-lg bg-blue-700 px-4 py-2 text-sm font-bold text-white hover:bg-blue-800">Profils de permissions</a>
            <?php endif; ?>
            <a href="<?= url('back-office') ?>" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Back-office</a>
        </div>
    </div>
    <p class="text-slate-600 text-sm mb-4">Rôles de gouvernance et opérationnels du tenant actif. Les rôles site/plateforme sont gérés sous « Administration système ». Utilisez <a href="<?= url('back-office/roles/presets') ?>" class="font-semibold text-blue-700 underline">Profils de permissions</a> pour appliquer un jeu complet de droits en une fois.</p>
    <div class="flex flex-wrap gap-2 mb-6 text-sm">
        <a href="<?= htmlspecialchars($base) ?>" class="px-3 py-1 rounded border <?= $roleLayerFilter === '' ? 'bg-slate-900 text-white border-slate-900' : 'border-slate-300 text-slate-700 hover:bg-slate-50' ?>">Tous</a>
        <a href="<?= htmlspecialchars($base . '?layer=community') ?>" class="px-3 py-1 rounded border <?= $roleLayerFilter === 'community' ? 'bg-slate-900 text-white border-slate-900' : 'border-slate-300 text-slate-700 hover:bg-slate-50' ?>">Gouvernance communauté</a>
        <a href="<?= htmlspecialchars($base . '?layer=intra') ?>" class="px-3 py-1 rounded border <?= $roleLayerFilter === 'intra' ? 'bg-slate-900 text-white border-slate-900' : 'border-slate-300 text-slate-700 hover:bg-slate-50' ?>">Rôles opérationnels</a>
    </div>
    <?php if (empty($roles)): ?>
    <p class="text-slate-500">Aucun rôle.</p>
    <?php else: ?>
    <table class="w-full border border-slate-200 rounded-lg overflow-hidden">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Nom</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Slug</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Couche</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Permissions</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($roles as $r):
                $rid = (int) $r['id'];
                $count = $permissionCounts[$rid] ?? 0;
                $layer = (string) ($r['role_layer'] ?? 'community');
            ?>
            <tr class="border-b border-slate-100 hover:bg-slate-50">
                <td class="p-3 font-medium"><?= htmlspecialchars($r['name']) ?></td>
                <td class="p-3 text-slate-600"><?= htmlspecialchars($r['slug']) ?></td>
                <td class="p-3 text-xs"><?= $layer === 'community' ? 'Communauté' : 'Intra' ?></td>
                <td class="p-3"><?= $count ?></td>
                <td class="p-3"><a href="<?= url('back-office/roles/' . $rid) ?>" class="text-slate-700 hover:underline text-sm">Voir</a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
