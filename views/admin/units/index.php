<?php
$units = $units ?? [];
$unitTypes = $unitTypes ?? [];
$typeLabel = function ($type) use ($unitTypes) {
    return $unitTypes[$type]['label'] ?? $type ?: '—';
};
?>
<div class="max-w-4xl mx-auto px-6 py-12">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-black text-slate-900">Unités / Équipes / Groupes</h1>
        <a href="<?= url('admin/units/create') ?>" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded hover:bg-slate-800">Nouvelle unité</a>
    </div>
    <?php if (\App\Core\Session::get('success')): ?>
    <p class="mb-4 text-sm text-emerald-600"><?= htmlspecialchars(\App\Core\Session::get('success')) ?></p>
    <?php \App\Core\Session::forget('success'); endif; ?>
    <?php if (\App\Core\Session::get('error')): ?>
    <p class="mb-4 text-sm text-red-600"><?= htmlspecialchars(\App\Core\Session::get('error')) ?></p>
    <?php \App\Core\Session::forget('error'); endif; ?>
    <?php if (empty($units)): ?>
    <p class="text-slate-500">Aucune unité. <a href="<?= url('admin/units/create') ?>" class="underline">Créer une unité, équipe ou groupe</a>.</p>
    <?php else: ?>
    <table class="w-full border border-slate-200 rounded-lg overflow-hidden">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Nom</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Type</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Code</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Ordre</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($units as $u): ?>
            <tr class="border-b border-slate-100 hover:bg-slate-50">
                <td class="p-3 font-medium"><?= htmlspecialchars($u['name']) ?></td>
                <td class="p-3"><?= htmlspecialchars($typeLabel($u['type'] ?? '')) ?></td>
                <td class="p-3"><?= htmlspecialchars($u['code'] ?? '—') ?></td>
                <td class="p-3"><?= (int) ($u['display_order'] ?? 0) ?></td>
                <td class="p-3">
                    <a href="<?= url('admin/units/' . $u['id'] . '/edit') ?>" class="text-slate-600 hover:text-slate-900 text-sm underline">Modifier</a>
                    <form action="<?= url('admin/units/' . $u['id'] . '/delete') ?>" method="post" class="inline ml-2" onsubmit="return confirm('Supprimer cette unité ?');">
                        <input type="hidden" name="_method" value="DELETE" />
                        <button type="submit" class="text-red-600 hover:text-red-800 text-sm underline">Supprimer</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <p class="mt-6 text-sm text-slate-500"><a href="<?= url('admin') ?>" class="underline">Retour administration</a> · <a href="<?= url('orbat') ?>" class="underline">Voir l’ORBAT</a></p>
</div>
