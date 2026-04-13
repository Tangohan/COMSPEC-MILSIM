<?php
$role = $role ?? null;
$rolePermissions = $rolePermissions ?? [];
$isLocked = $isLocked ?? false;
if (!$role) {
    echo '<p>Rôle introuvable.</p>';
    return;
}
$rid = (int) $role['id'];
?>
<div class="max-w-4xl mx-auto px-6 py-12">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-black text-slate-900"><?= htmlspecialchars($role['name']) ?></h1>
        <div class="flex gap-2">
            <a href="<?= url('admin/roles') ?>" class="text-sm font-medium text-slate-600 hover:underline">Liste des rôles</a>
            <?php if (!$isLocked): ?>
            <a href="<?= url('admin/roles/' . $rid . '/edit') ?>" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded hover:bg-slate-800">Modifier les permissions</a>
            <?php endif; ?>
        </div>
    </div>
    <dl class="grid gap-4 md:grid-cols-2 mb-6">
        <div><dt class="text-slate-500 text-sm">Référence interne du rôle</dt><dd class="font-mono"><?= htmlspecialchars($role['slug']) ?></dd></div>
        <div><dt class="text-slate-500 text-sm">Description</dt><dd><?= htmlspecialchars($role['description'] ?? '—') ?></dd></div>
        <div><dt class="text-slate-500 text-sm">Système</dt><dd><?= !empty($role['is_system']) ? 'Oui' : 'Non' ?></dd></div>
        <div><dt class="text-slate-500 text-sm">Verrouillé</dt><dd><?= $isLocked ? 'Oui' : 'Non' ?></dd></div>
        <div><dt class="text-slate-500 text-sm">Couche</dt><dd><?= htmlspecialchars((string) ($role['role_layer'] ?? 'site')) ?> (site / plateforme)</dd></div>
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
