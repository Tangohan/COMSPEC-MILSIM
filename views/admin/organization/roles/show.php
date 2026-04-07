<?php
$role = $role ?? null;
$rolePermissions = $rolePermissions ?? [];
if (!$role) {
    echo '<p>Rôle introuvable.</p>';
    return;
}
$rid = (int) $role['id'];
$__g = \App\Core\Gate::getInstance();
$roleLocked = (int) ($role['is_locked'] ?? 0) !== 0;
$canEditPermissions = ($__g->allows('admin.organization') || $__g->allows('admin.roles.manage') || $__g->allows('admin.permissions.manage')) && !$roleLocked;
?>
<div class="max-w-4xl mx-auto px-6 py-12">
    <div class="mb-6 rounded-lg border border-blue-100 bg-blue-50/90 px-4 py-3 text-sm text-slate-800">
        Habilitations du rôle au sein de <strong class="font-semibold">votre communauté</strong>. Les rôles réservés à la plateforme ne sont pas modifiables depuis cet espace.
    </div>
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <h1 class="text-2xl font-black text-slate-900"><?= htmlspecialchars($role['name']) ?></h1>
        <div class="flex flex-wrap items-center gap-4">
            <?php if ($canEditPermissions): ?>
            <a href="<?= url('back-office/roles/' . $rid . '/permissions') ?>" class="text-sm font-semibold text-emerald-700 hover:underline">Modifier les habilitations</a>
            <?php endif; ?>
            <a href="<?= url('back-office/roles/' . $rid . '/edit-presentation') ?>" class="text-sm font-semibold text-blue-700 hover:underline">Modifier présentation</a>
            <a href="<?= url('back-office/roles') ?>" class="text-sm font-medium text-slate-600 hover:underline">Liste des rôles</a>
        </div>
    </div>
    <?php
    $tier = (string) ($role['semantic_tier'] ?? 'function');
    $tierHuman = match ($tier) {
        'authority' => 'Commandement',
        'function' => 'Emploi',
        'liaison' => 'Liaison',
        'support' => 'Soutien',
        'specialty' => 'Spécialité',
        'status' => 'Statut affiché',
        default => 'Emploi',
    };
    ?>
    <dl class="grid gap-4 md:grid-cols-2 mb-6">
        <div><dt class="text-slate-500 text-sm">Référence courte</dt><dd class="font-mono text-sm text-slate-800"><?= htmlspecialchars($role['slug']) ?></dd></div>
        <div><dt class="text-slate-500 text-sm">Description</dt><dd><?= htmlspecialchars($role['description'] ?? '—') ?></dd></div>
        <?php if (!empty($role['category'])): ?>
        <div><dt class="text-slate-500 text-sm">Famille</dt><dd class="text-slate-800"><?= htmlspecialchars((string) $role['category']) ?></dd></div>
        <?php endif; ?>
        <?php if (!empty($role['subcategory'])): ?>
        <div><dt class="text-slate-500 text-sm">Sous-ensemble</dt><dd class="text-slate-800"><?= htmlspecialchars((string) $role['subcategory']) ?></dd></div>
        <?php endif; ?>
        <div><dt class="text-slate-500 text-sm">Type de rôle</dt><dd class="text-slate-800"><?= htmlspecialchars($tierHuman) ?></dd></div>
        <?php if (!empty($role['label_en'])): ?>
        <div class="md:col-span-2"><dt class="text-slate-500 text-sm">Libellé en anglais (affichage bilingue)</dt><dd class="text-slate-800"><?= htmlspecialchars((string) $role['label_en']) ?></dd></div>
        <?php endif; ?>
    </dl>
    <h2 class="text-lg font-bold text-slate-900 mb-3">Permissions associées</h2>
    <?php if (empty($rolePermissions)): ?>
    <p class="text-slate-500">Aucune permission.</p>
    <?php else: ?>
    <ul class="space-y-1">
        <?php foreach ($rolePermissions as $p): ?>
        <li class="text-sm text-slate-800"><?= htmlspecialchars($p['name']) ?></li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>
