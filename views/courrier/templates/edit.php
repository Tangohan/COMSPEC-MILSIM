<?php
$template = $courrier['template'] ?? null;
$presets = $courrier['presets'] ?? [];
$baseUrl = url('');
$isNew = $template === null;
?>
<div class="max-w-4xl mx-auto px-6 py-12">
    <div class="mb-6">
        <a href="<?= $baseUrl ?>/courrier/templates" class="text-slate-500 hover:text-slate-900 text-sm">← Modèles</a>
    </div>
    <h1 class="text-2xl font-black text-slate-900 mb-6"><?= $isNew ? 'Nouveau modèle' : 'Modifier le modèle' ?></h1>

    <?php if (\App\Core\Session::get('success')): ?>
    <p class="mb-4 text-sm text-emerald-600"><?= htmlspecialchars((string)\App\Core\Session::get('success')) ?></p>
    <?php \App\Core\Session::forget('success'); endif; ?>

    <form method="post" action="<?= $isNew ? $baseUrl . '/courrier/templates' : $baseUrl . '/courrier/templates/' . (int)$template['id'] ?>" class="space-y-4">
        <?= \App\Core\Csrf::field() ?>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Nom</label>
            <input type="text" name="name" value="<?= htmlspecialchars($template['name'] ?? '') ?>" class="w-full border border-slate-200 rounded px-3 py-2" required>
        </div>
        <?php if (!$isNew): ?>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Slug</label>
            <input type="text" name="slug" value="<?= htmlspecialchars($template['slug'] ?? '') ?>" class="w-full border border-slate-200 rounded px-3 py-2">
        </div>
        <?php endif; ?>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Format (preset)</label>
            <select name="preset_id" class="w-full border border-slate-200 rounded px-3 py-2">
                <option value="">— Aucun —</option>
                <?php foreach ($presets as $p): ?>
                <option value="<?= (int)$p['id'] ?>" <?= $template && (int)($template['preset_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['name'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Catégorie</label>
            <input type="text" name="category" value="<?= htmlspecialchars($template['category'] ?? '') ?>" class="w-full border border-slate-200 rounded px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
            <textarea name="description" rows="2" class="w-full border border-slate-200 rounded px-3 py-2"><?= htmlspecialchars($template['description'] ?? '') ?></textarea>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Corps du modèle (variables {{user.first_name}}, {{unit.name}}, etc.)</label>
            <textarea name="body_template" rows="12" class="w-full border border-slate-200 rounded px-3 py-2 font-mono text-sm"><?= htmlspecialchars($template['body_template'] ?? '') ?></textarea>
        </div>
        <button type="submit" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded hover:bg-slate-800"><?= $isNew ? 'Créer' : 'Enregistrer' ?></button>
    </form>
</div>
