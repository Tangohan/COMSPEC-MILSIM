<?php $roles = $roles ?? []; $permissionCounts = $permissionCounts ?? []; ?>
<div class="max-w-4xl mx-auto px-6 py-12">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-black text-slate-900">Rôles site (plateforme)</h1>
        <a href="<?= url('admin/system') ?>" class="text-sm font-medium text-slate-600 hover:underline">Retour administration système</a>
    </div>
    <?php if (empty($roles)): ?>
    <p class="text-slate-500">Aucun rôle.</p>
    <?php else: ?>
    <table class="w-full border border-slate-200 rounded-lg overflow-hidden">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Nom</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Slug</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Type</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Permissions</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($roles as $r):
                $rid = (int) $r['id'];
                $count = $permissionCounts[$rid] ?? 0;
                $isLocked = !empty($r['is_locked']);
                $isSystem = !empty($r['is_system']);
            ?>
            <tr class="border-b border-slate-100 hover:bg-slate-50">
                <td class="p-3 font-medium"><?= htmlspecialchars($r['name']) ?></td>
                <td class="p-3 text-slate-600"><?= htmlspecialchars($r['slug']) ?></td>
                <td class="p-3">
                    <?php if ($isSystem): ?><span class="px-2 py-0.5 text-xs rounded bg-amber-100 text-amber-800">Système</span><?php endif; ?>
                    <?php if ($isLocked): ?><span class="px-2 py-0.5 text-xs rounded bg-slate-200 text-slate-600">Verrouillé</span><?php endif; ?>
                </td>
                <td class="p-3"><?= $count ?></td>
                <td class="p-3">
                    <a href="<?= url('admin/system/roles/' . $rid) ?>" class="text-slate-700 hover:underline text-sm">Voir</a>
                    <?php if (!$isLocked): ?>
                    <span class="mx-1">|</span>
                    <a href="<?= url('admin/system/roles/' . $rid . '/edit') ?>" class="text-slate-700 hover:underline text-sm">Modifier</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
