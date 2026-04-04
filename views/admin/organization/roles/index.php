<?php $roles = $roles ?? []; $permissionCounts = $permissionCounts ?? []; ?>
<div class="max-w-4xl mx-auto px-6 py-12">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-black text-slate-900">Rôles</h1>
        <a href="<?= url('admin/organization') ?>" class="text-sm font-medium text-slate-600 hover:underline">Retour administration organisationnelle</a>
    </div>
    <p class="text-slate-600 text-sm mb-6">Consultation des rôles et permissions. La modification des permissions est réservée à l'administration système.</p>
    <?php if (empty($roles)): ?>
    <p class="text-slate-500">Aucun rôle.</p>
    <?php else: ?>
    <table class="w-full border border-slate-200 rounded-lg overflow-hidden">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Nom</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Slug</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Permissions</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($roles as $r):
                $rid = (int) $r['id'];
                $count = $permissionCounts[$rid] ?? 0;
            ?>
            <tr class="border-b border-slate-100 hover:bg-slate-50">
                <td class="p-3 font-medium"><?= htmlspecialchars($r['name']) ?></td>
                <td class="p-3 text-slate-600"><?= htmlspecialchars($r['slug']) ?></td>
                <td class="p-3"><?= $count ?></td>
                <td class="p-3"><a href="<?= url('admin/organization/roles/' . $rid) ?>" class="text-slate-700 hover:underline text-sm">Voir</a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
