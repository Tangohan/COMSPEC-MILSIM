<?php
$unit = $unit ?? [];
$parents = $parents ?? [];
$users = $users ?? [];
$unitTypes = $unitTypes ?? [];
?>
<div class="max-w-2xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-6">Modifier l’unité</h1>
    <?php if (\App\Core\Session::get('error')): ?>
    <p class="mb-4 text-sm text-red-600"><?= htmlspecialchars(\App\Core\Session::get('error')) ?></p>
    <?php \App\Core\Session::forget('error'); endif; ?>
    <form action="<?= url('admin/units/' . ($unit['id'] ?? '') . '/update') ?>" method="post" class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Nom *</label>
            <input type="text" name="name" required class="w-full border border-slate-200 rounded px-3 py-2" value="<?= htmlspecialchars($unit['name'] ?? '') ?>" />
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Slug</label>
            <input type="text" name="slug" class="w-full border border-slate-200 rounded px-3 py-2" value="<?= htmlspecialchars($unit['slug'] ?? '') ?>" />
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Type</label>
            <select name="type" class="w-full border border-slate-200 rounded px-3 py-2">
                <option value="">—</option>
                <?php foreach ($unitTypes as $k => $v): ?>
                <option value="<?= htmlspecialchars($k) ?>" <?= ($unit['type'] ?? '') === $k ? 'selected' : '' ?>><?= htmlspecialchars($v['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Code</label>
            <input type="text" name="code" class="w-full border border-slate-200 rounded px-3 py-2" value="<?= htmlspecialchars($unit['code'] ?? '') ?>" />
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Unité parente</label>
            <select name="parent_id" class="w-full border border-slate-200 rounded px-3 py-2">
                <option value="">— Aucune</option>
                <?php foreach ($parents as $p): ?>
                <option value="<?= (int) $p['id'] ?>" <?= ((int)($unit['parent_id'] ?? 0)) === (int)$p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Commandant</label>
            <select name="commander_user_id" class="w-full border border-slate-200 rounded px-3 py-2">
                <option value="">— Aucun</option>
                <?php foreach ($users as $u): ?>
                <option value="<?= (int) $u['id'] ?>" <?= ((int)($unit['commander_user_id'] ?? 0)) === (int)$u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['display_name'] ?? $u['email']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Ordre d’affichage</label>
            <input type="number" name="display_order" class="w-full border border-slate-200 rounded px-3 py-2" value="<?= (int) ($unit['display_order'] ?? 0) ?>" />
        </div>
        <div class="flex gap-3">
            <button type="submit" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded hover:bg-slate-800">Enregistrer</button>
            <a href="<?= url('admin/units') ?>" class="px-4 py-2 border border-slate-200 text-slate-700 text-sm rounded hover:bg-slate-50">Annuler</a>
        </div>
    </form>
</div>
