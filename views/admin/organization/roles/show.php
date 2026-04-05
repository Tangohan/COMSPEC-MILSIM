<?php $role = $role ?? null; $rolePermissions = $rolePermissions ?? []; if (!$role) { echo '<p>Rôle introuvable.</p>'; return; } $rid = (int) $role['id']; ?>
<div class="max-w-4xl mx-auto px-6 py-12">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-black text-slate-900"><?= htmlspecialchars($role['name']) ?></h1>
        <a href="<?= url('back-office/roles') ?>" class="text-sm font-medium text-slate-600 hover:underline">Liste des rôles</a>
    </div>
    <dl class="grid gap-4 md:grid-cols-2 mb-6">
        <div><dt class="text-slate-500 text-sm">Slug</dt><dd class="font-mono"><?= htmlspecialchars($role['slug']) ?></dd></div>
        <div><dt class="text-slate-500 text-sm">Description</dt><dd><?= htmlspecialchars($role['description'] ?? '—') ?></dd></div>
    </dl>
    <h2 class="text-lg font-bold text-slate-900 mb-3">Permissions associées</h2>
    <?php if (empty($rolePermissions)): ?>
    <p class="text-slate-500">Aucune permission.</p>
    <?php else: ?>
    <ul class="space-y-1">
        <?php foreach ($rolePermissions as $p): ?>
        <li class="text-sm"><span class="font-mono text-slate-700"><?= htmlspecialchars($p['slug']) ?></span> — <?= htmlspecialchars($p['name']) ?></li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>
