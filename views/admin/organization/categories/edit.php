<?php $category = $category ?? null; if (!$category) { echo '<p>Catégorie introuvable.</p>'; return; } $id = (int) $category['id']; ?>
<div class="max-w-2xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-6">Modifier la catégorie</h1>
    <form method="post" action="<?= url('admin/organization/categories/' . $id . '/update') ?>" class="space-y-4">
        <?= \App\Core\Csrf::field() ?>
        <div>
            <label for="name" class="block text-sm font-medium text-slate-700">Nom *</label>
            <input type="text" id="name" name="name" required class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm" value="<?= htmlspecialchars($category['name'] ?? '') ?>">
        </div>
        <div>
            <label for="slug" class="block text-sm font-medium text-slate-700">Slug</label>
            <input type="text" id="slug" name="slug" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm" value="<?= htmlspecialchars($category['slug'] ?? '') ?>">
        </div>
        <div>
            <label for="type" class="block text-sm font-medium text-slate-700">Type</label>
            <select id="type" name="type" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm">
                <option value="organizational" <?= ($category['type'] ?? '') === 'organizational' ? 'selected' : '' ?>>Organisationnelle</option>
                <option value="role" <?= ($category['type'] ?? '') === 'role' ? 'selected' : '' ?>>Rôles</option>
                <option value="user" <?= ($category['type'] ?? '') === 'user' ? 'selected' : '' ?>>Utilisateurs</option>
                <option value="business" <?= ($category['type'] ?? '') === 'business' ? 'selected' : '' ?>>Métier</option>
            </select>
        </div>
        <div>
            <label for="description" class="block text-sm font-medium text-slate-700">Description</label>
            <textarea id="description" name="description" rows="2" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm"><?= htmlspecialchars($category['description'] ?? '') ?></textarea>
        </div>
        <div>
            <label for="color" class="block text-sm font-medium text-slate-700">Couleur</label>
            <input type="text" id="color" name="color" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm" value="<?= htmlspecialchars($category['color'] ?? '') ?>">
        </div>
        <div>
            <label for="display_order" class="block text-sm font-medium text-slate-700">Ordre d'affichage</label>
            <input type="number" id="display_order" name="display_order" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm" value="<?= (int) ($category['display_order'] ?? 0) ?>">
        </div>
        <div>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="is_active" value="1" <?= !empty($category['is_active']) ? 'checked' : '' ?>>
                <span class="text-sm font-medium text-slate-700">Active</span>
            </label>
        </div>
        <div class="flex gap-3 pt-4">
            <button type="submit" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded hover:bg-slate-800">Enregistrer</button>
            <a href="<?= url('admin/organization/categories') ?>" class="px-4 py-2 text-slate-600 text-sm hover:underline">Annuler</a>
        </div>
    </form>
</div>
