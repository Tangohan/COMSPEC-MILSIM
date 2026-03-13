<?php $users = $users ?? []; ?>
<div class="max-w-4xl mx-auto px-6 py-12">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-black text-slate-900">Utilisateurs</h1>
        <a href="<?= url('admin/users/create') ?>" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded hover:bg-slate-800">Nouvel utilisateur</a>
    </div>
    <?php if (empty($users)): ?>
    <p class="text-slate-500">Aucun utilisateur.</p>
    <?php else: ?>
    <table class="w-full border border-slate-200 rounded-lg overflow-hidden">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Email</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Nom</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Rôle</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Statut</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
            <tr class="border-b border-slate-100 hover:bg-slate-50">
                <td class="p-3"><?= htmlspecialchars($u['email']) ?></td>
                <td class="p-3"><?= htmlspecialchars($u['display_name'] ?? '—') ?></td>
                <td class="p-3"><?= htmlspecialchars($u['role_name'] ?? '—') ?></td>
                <td class="p-3"><?= htmlspecialchars($u['status'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <p class="mt-6 text-sm text-slate-500"><a href="<?= url('admin') ?>" class="underline">Retour administration</a></p>
</div>
