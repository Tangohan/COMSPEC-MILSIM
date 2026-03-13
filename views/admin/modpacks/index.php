<?php
$modpacks = $modpacks ?? [];
$formatSize = function ($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 1, ',', ' ') . ' Go';
    }
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1, ',', ' ') . ' Mo';
    }
    return $bytes ? (number_format($bytes / 1024, 1, ',', ' ') . ' Ko') : '—';
};
?>
<div class="max-w-4xl mx-auto px-6 py-12">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-black text-slate-900">Modpacks</h1>
        <a href="<?= url('admin/modpacks/create') ?>" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded hover:bg-slate-800">Nouveau modpack</a>
    </div>
    <?php if (\App\Core\Session::get('success')): ?>
    <p class="mb-4 text-sm text-emerald-600"><?= htmlspecialchars(\App\Core\Session::get('success')) ?></p>
    <?php \App\Core\Session::forget('success'); endif; ?>
    <?php if (\App\Core\Session::get('error')): ?>
    <p class="mb-4 text-sm text-red-600"><?= htmlspecialchars(\App\Core\Session::get('error')) ?></p>
    <?php \App\Core\Session::forget('error'); endif; ?>
    <?php if (empty($modpacks)): ?>
    <p class="text-slate-500">Aucun modpack. <a href="<?= url('admin/modpacks/create') ?>" class="underline">Créer un modpack</a>.</p>
    <?php else: ?>
    <table class="w-full border border-slate-200 rounded-lg overflow-hidden">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Nom</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Version</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Taille</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Mise à jour</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($modpacks as $mp): ?>
            <tr class="border-b border-slate-100 hover:bg-slate-50">
                <td class="p-3 font-medium"><?= htmlspecialchars($mp['name']) ?></td>
                <td class="p-3"><?= htmlspecialchars($mp['version'] ?? '—') ?></td>
                <td class="p-3"><?= $formatSize((int) ($mp['size'] ?? 0)) ?></td>
                <td class="p-3"><?= !empty($mp['updated_at']) ? date('d.m.Y', strtotime($mp['updated_at'])) : '—' ?></td>
                <td class="p-3">
                    <a href="<?= url('modpacks/' . htmlspecialchars($mp['slug'])) ?>" class="text-slate-600 hover:text-slate-900 text-sm underline">Voir</a>
                    <a href="<?= url('admin/modpacks/' . $mp['id'] . '/edit') ?>" class="text-slate-600 hover:text-slate-900 text-sm underline ml-2">Modifier</a>
                    <form action="<?= url('admin/modpacks/' . $mp['id'] . '/delete') ?>" method="post" class="inline ml-2" onsubmit="return confirm('Supprimer ce modpack ?');">
                        <?= \App\Core\Csrf::field() ?>
                        <button type="submit" class="text-red-600 hover:text-red-800 text-sm underline">Supprimer</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <p class="mt-6 text-sm text-slate-500"><a href="<?= url('admin') ?>" class="underline">Retour administration</a></p>
</div>
