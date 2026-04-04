<?php $group = $group ?? null; $parents = $parents ?? []; $users = $users ?? []; if (!$group) { echo '<p>Groupe introuvable.</p>'; return; } $gid = (int) $group['id']; ?>
<div class="max-w-2xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-6">Modifier le groupe</h1>
    <form method="post" action="<?= url('admin/organization/groups/' . $gid . '/update') ?>" class="space-y-4">
        <?= \App\Core\Csrf::field() ?>
        <div>
            <label for="name" class="block text-sm font-medium text-slate-700">Nom *</label>
            <input type="text" id="name" name="name" required class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm" value="<?= htmlspecialchars($group['name'] ?? '') ?>">
        </div>
        <div>
            <label for="slug" class="block text-sm font-medium text-slate-700">Slug</label>
            <input type="text" id="slug" name="slug" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm" value="<?= htmlspecialchars($group['slug'] ?? '') ?>">
        </div>
        <div>
            <label for="code" class="block text-sm font-medium text-slate-700">Code</label>
            <input type="text" id="code" name="code" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm" value="<?= htmlspecialchars($group['code'] ?? '') ?>">
        </div>
        <div>
            <label for="parent_id" class="block text-sm font-medium text-slate-700">Parent</label>
            <select id="parent_id" name="parent_id" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm">
                <option value="">—</option>
                <?php foreach ($parents as $p): ?>
                <option value="<?= (int) $p['id'] ?>" <?= (int) ($group['parent_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="commander_user_id" class="block text-sm font-medium text-slate-700">Responsable</label>
            <select id="commander_user_id" name="commander_user_id" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm">
                <option value="">—</option>
                <?php foreach ($users as $u): ?>
                <option value="<?= (int) $u['id'] ?>" <?= (int) ($group['commander_user_id'] ?? 0) === (int) $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['display_name'] ?? $u['email']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="display_order" class="block text-sm font-medium text-slate-700">Ordre</label>
            <input type="number" id="display_order" name="display_order" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm" value="<?= (int) ($group['display_order'] ?? 0) ?>">
        </div>
        <div class="flex gap-3 pt-4">
            <button type="submit" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded hover:bg-slate-800">Enregistrer</button>
            <a href="<?= url('admin/organization/groups/' . $gid) ?>" class="px-4 py-2 text-slate-600 text-sm hover:underline">Annuler</a>
        </div>
    </form>
</div>
