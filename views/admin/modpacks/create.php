<?php
$name = $_POST['name'] ?? '';
$slug = $_POST['slug'] ?? '';
$version = $_POST['version'] ?? '';
$description = $_POST['description'] ?? '';
?>
<div class="max-w-2xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-6">Nouveau modpack</h1>
    <?php if (\App\Core\Session::get('error')): ?>
    <p class="mb-4 text-sm text-red-600"><?= htmlspecialchars(\App\Core\Session::get('error')) ?></p>
    <?php \App\Core\Session::forget('error'); endif; ?>
    <form action="<?= url('admin/modpacks/store') ?>" method="post" enctype="multipart/form-data" class="space-y-4">
        <?= \App\Core\Csrf::field() ?>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Nom *</label>
            <input type="text" name="name" required class="w-full border border-slate-200 rounded px-3 py-2" value="<?= htmlspecialchars($name) ?>" />
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Adresse courte (optionnel, générée si vide)</label>
            <input type="text" name="slug" class="w-full border border-slate-200 rounded px-3 py-2" placeholder="ex: modpack-principal" value="<?= htmlspecialchars($slug) ?>" />
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Version (ex. V1.2.3-STABLE)</label>
            <input type="text" name="version" class="w-full border border-slate-200 rounded px-3 py-2" placeholder="V1.0.0-STABLE" value="<?= htmlspecialchars($version) ?>" />
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
            <textarea name="description" rows="4" class="w-full border border-slate-200 rounded px-3 py-2"><?= htmlspecialchars($description) ?></textarea>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Fichier modpack (ZIP, RAR, 7z — max 2 Go)</label>
            <input type="file" name="modpack_file" accept=".zip,.rar,.7z,application/zip,application/x-rar-compressed,application/x-7z-compressed" class="w-full border border-slate-200 rounded px-3 py-2" />
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Images (JPG, PNG, WebP — max 5 Mo chacune)</label>
            <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple class="w-full border border-slate-200 rounded px-3 py-2" />
        </div>
        <div class="flex gap-3">
            <button type="submit" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded hover:bg-slate-800">Créer</button>
            <a href="<?= url('admin/modpacks') ?>" class="px-4 py-2 border border-slate-200 text-slate-700 text-sm rounded hover:bg-slate-50">Annuler</a>
        </div>
    </form>
</div>
