<?php $teams = $teams ?? []; ?>
<div class="max-w-4xl mx-auto px-6 py-12">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-black text-slate-900">Équipes</h1>
        <a href="<?= url('admin/organization/teams/create') ?>" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded hover:bg-slate-800">Nouvelle équipe</a>
    </div>
    <?php if (\App\Core\Session::get('success')): ?><p class="mb-4 text-sm text-emerald-600"><?= htmlspecialchars(\App\Core\Session::get('success')) ?></p><?php \App\Core\Session::forget('success'); endif; ?>
    <?php if (\App\Core\Session::get('error')): ?><p class="mb-4 text-sm text-rose-600"><?= htmlspecialchars(\App\Core\Session::get('error')) ?></p><?php \App\Core\Session::forget('error'); endif; ?>
    <?php if (empty($teams)): ?>
    <p class="text-slate-500">Aucune équipe.</p>
    <?php else: ?>
    <table class="w-full border border-slate-200 rounded-lg overflow-hidden">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Nom</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Code</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($teams as $t): ?>
            <tr class="border-b border-slate-100 hover:bg-slate-50">
                <td class="p-3 font-medium"><?= htmlspecialchars($t['name']) ?></td>
                <td class="p-3"><?= htmlspecialchars($t['code'] ?? '—') ?></td>
                <td class="p-3">
                    <a href="<?= url('admin/organization/teams/' . $t['id']) ?>" class="text-slate-600 hover:underline text-sm">Voir</a>
                    <span class="mx-1">|</span>
                    <a href="<?= url('admin/organization/teams/' . $t['id'] . '/edit') ?>" class="text-slate-600 hover:underline text-sm">Modifier</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <p class="mt-6 text-sm text-slate-500"><a href="<?= url('admin/organization') ?>" class="underline">Retour administration organisationnelle</a></p>
</div>
