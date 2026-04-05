<?php $categories = $categories ?? []; $filterType = $filterType ?? ''; ?>
<div class="max-w-4xl mx-auto px-6 py-12">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-black text-slate-900">Catégories</h1>
        <a href="<?= url('back-office/categories/create') ?>" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded hover:bg-slate-800">Nouvelle catégorie</a>
    </div>
    <form method="get" action="<?= url('back-office/categories') ?>" class="mb-6 flex gap-2">
        <select name="type" class="px-3 py-2 border border-slate-200 rounded text-sm">
            <option value="">Tous les types</option>
            <option value="role" <?= $filterType === 'role' ? 'selected' : '' ?>>Rôles</option>
            <option value="user" <?= $filterType === 'user' ? 'selected' : '' ?>>Utilisateurs</option>
            <option value="organizational" <?= $filterType === 'organizational' ? 'selected' : '' ?>>Organisationnelle</option>
            <option value="business" <?= $filterType === 'business' ? 'selected' : '' ?>>Métier</option>
        </select>
        <button type="submit" class="px-4 py-2 bg-slate-700 text-white text-sm rounded hover:bg-slate-600">Filtrer</button>
    </form>
    <?php if (empty($categories)): ?>
    <p class="text-slate-500">Aucune catégorie.</p>
    <?php else: ?>
    <table class="w-full border border-slate-200 rounded-lg overflow-hidden">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Nom</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Type</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Couleur</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Ordre</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($categories as $c): ?>
            <tr class="border-b border-slate-100 hover:bg-slate-50">
                <td class="p-3 font-medium"><?= htmlspecialchars($c['name']) ?></td>
                <td class="p-3 text-slate-600"><?= htmlspecialchars($c['type'] ?? '—') ?></td>
                <td class="p-3"><?= !empty($c['color']) ? '<span class="inline-block w-4 h-4 rounded border border-slate-300" style="background:' . htmlspecialchars($c['color']) . '"></span>' : '—' ?></td>
                <td class="p-3"><?= (int) ($c['display_order'] ?? 0) ?></td>
                <td class="p-3">
                    <a href="<?= url('back-office/categories/' . $c['id'] . '/edit') ?>" class="text-slate-700 hover:underline text-sm">Modifier</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <p class="mt-6 text-sm text-slate-500"><a href="<?= url('back-office') ?>" class="underline">Retour administration organisationnelle</a></p>
</div>
