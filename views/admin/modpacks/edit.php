<?php
$modpack = $modpack ?? [];
$images = $modpack['images'] ?? [];
?>
<div class="max-w-2xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-6">Modifier le modpack</h1>
    <?php if (\App\Core\Session::get('error')): ?>
    <p class="mb-4 text-sm text-red-600"><?= htmlspecialchars(\App\Core\Session::get('error')) ?></p>
    <?php \App\Core\Session::forget('error'); endif; ?>
    <form action="<?= url('admin/modpacks/' . ($modpack['id'] ?? '') . '/update') ?>" method="post" enctype="multipart/form-data" class="space-y-4">
        <?= \App\Core\Csrf::field() ?>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Nom *</label>
            <input type="text" name="name" required class="w-full border border-slate-200 rounded px-3 py-2" value="<?= htmlspecialchars($modpack['name'] ?? '') ?>" />
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Adresse courte dans l’URL</label>
            <input type="text" name="slug" class="w-full border border-slate-200 rounded px-3 py-2" value="<?= htmlspecialchars($modpack['slug'] ?? '') ?>" />
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Version (ex. V1.2.3-STABLE)</label>
            <input type="text" name="version" class="w-full border border-slate-200 rounded px-3 py-2" value="<?= htmlspecialchars($modpack['version'] ?? '') ?>" />
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
            <textarea name="description" rows="4" class="w-full border border-slate-200 rounded px-3 py-2"><?= htmlspecialchars($modpack['description'] ?? '') ?></textarea>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Fichier modpack (remplacer — ZIP, RAR, 7z, max 2 Go)</label>
            <input type="file" name="modpack_file" accept=".zip,.rar,.7z,application/zip,application/x-rar-compressed,application/x-7z-compressed" class="w-full border border-slate-200 rounded px-3 py-2" />
            <?php if (!empty($modpack['file_path'])): ?>
            <p class="mt-1 text-xs text-slate-500">Fichier actuel enregistré. En choisir un nouveau le remplace.</p>
            <?php endif; ?>
        </div>
        <?php if (!empty($images)): ?>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Images existantes (cocher pour supprimer)</label>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                <?php foreach ($images as $img): ?>
                <div class="border border-slate-200 rounded p-2 flex flex-col items-center">
                    <img src="<?= url('modpacks/images/' . $img['id']) ?>" alt="" class="w-full h-20 object-cover rounded" />
                    <label class="mt-2 flex items-center gap-1 text-sm text-red-600">
                        <input type="checkbox" name="delete_image[]" value="<?= (int) $img['id'] ?>" />
                        Supprimer
                    </label>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Nouvelles images (JPG, PNG, WebP — max 5 Mo chacune)</label>
            <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple class="w-full border border-slate-200 rounded px-3 py-2" />
        </div>
        <div class="flex gap-3">
            <button type="submit" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded hover:bg-slate-800">Enregistrer</button>
            <a href="<?= url('admin/modpacks') ?>" class="px-4 py-2 border border-slate-200 text-slate-700 text-sm rounded hover:bg-slate-50">Annuler</a>
        </div>
    </form>
</div>
