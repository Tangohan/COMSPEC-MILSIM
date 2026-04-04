<?php $group = $group ?? null; $members = $members ?? []; $commander = $commander ?? null; if (!$group) { echo '<p>Groupe introuvable.</p>'; return; } $gid = (int) $group['id']; ?>
<div class="max-w-4xl mx-auto px-6 py-12">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-black text-slate-900"><?= htmlspecialchars($group['name']) ?></h1>
        <div class="flex gap-2">
            <a href="<?= url('admin/organization/groups/' . $gid . '/edit') ?>" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded hover:bg-slate-800">Modifier</a>
            <form method="post" action="<?= url('admin/organization/groups/' . $gid . '/delete') ?>" class="inline" onsubmit="return confirm('Supprimer ce groupe ?');">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="px-4 py-2 bg-rose-100 text-rose-800 text-sm font-semibold rounded hover:bg-rose-200">Supprimer</button>
            </form>
        </div>
    </div>
    <dl class="grid gap-4 md:grid-cols-2 mb-6">
        <div><dt class="text-slate-500 text-sm">Slug</dt><dd><?= htmlspecialchars($group['slug'] ?? '—') ?></dd></div>
        <div><dt class="text-slate-500 text-sm">Code</dt><dd><?= htmlspecialchars($group['code'] ?? '—') ?></dd></div>
        <div><dt class="text-slate-500 text-sm">Responsable</dt><dd><?= $commander ? htmlspecialchars($commander['display_name'] ?? $commander['email']) : '—' ?></dd></div>
    </dl>
    <h2 class="text-lg font-bold text-slate-900 mb-3">Membres</h2>
    <?php if (empty($members)): ?>
    <p class="text-slate-500">Aucun membre.</p>
    <?php else: ?>
    <ul class="space-y-1">
        <?php foreach ($members as $m): ?>
        <li><a href="<?= url('admin/organization/users/' . $m['id']) ?>" class="text-slate-700 hover:underline"><?= htmlspecialchars($m['display_name'] ?? $m['email']) ?></a></li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <p class="mt-8 text-sm text-slate-500"><a href="<?= url('admin/organization/groups') ?>" class="underline">Retour aux groupes</a></p>
</div>
