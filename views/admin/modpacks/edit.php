<?php
$modpack = $modpack ?? [];
$images = $modpack['images'] ?? [];
$limits = is_array($modpackUploadLimits ?? null) ? $modpackUploadLimits : [
    'max_bytes' => 2 * 1024 * 1024 * 1024,
    'chunk_bytes' => 8 * 1024 * 1024,
    'max_label' => '2 Go',
    'extensions' => ['zip', 'rar', '7z'],
];
$limitsJson = htmlspecialchars(json_encode($limits, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}', ENT_QUOTES, 'UTF-8');
$hasFile = !empty($modpack['file_path']);
$sizeLabel = '';
if (!empty($modpack['size'])) {
    $bytes = (int) $modpack['size'];
    if ($bytes >= 1073741824) {
        $sizeLabel = number_format($bytes / 1073741824, 1, ',', ' ') . ' Go';
    } elseif ($bytes >= 1048576) {
        $sizeLabel = number_format($bytes / 1048576, 1, ',', ' ') . ' Mo';
    } else {
        $sizeLabel = number_format(max($bytes, 0) / 1024, 1, ',', ' ') . ' Ko';
    }
}
?>
<link rel="stylesheet" href="<?= htmlspecialchars(asset_url('assets/css/modpack-admin.css'), ENT_QUOTES, 'UTF-8') ?>">
<div class="mp-admin bo-legacy bo-legacy--2xl">
    <h1 class="text-2xl font-black text-slate-900 mb-2">Modifier le modpack</h1>
    <p class="mp-admin__lead">
        Remplacez l’archive, ajustez la version ou ajoutez un lien externe.
        L’ancien fichier est retiré automatiquement lors d’un remplacement réussi.
    </p>
    <?php if (\App\Core\Session::get('error')): ?>
    <p class="mb-4 text-sm text-red-600"><?= htmlspecialchars(\App\Core\Session::get('error')) ?></p>
    <?php \App\Core\Session::forget('error'); endif; ?>
    <form
        action="<?= url('admin/modpacks/' . ($modpack['id'] ?? '') . '/update') ?>"
        method="post"
        enctype="multipart/form-data"
        class="space-y-4"
        data-modpack-upload
        data-upload-limits="<?= $limitsJson ?>"
        data-upload-init="<?= htmlspecialchars(url('admin/modpacks/upload/init'), ENT_QUOTES, 'UTF-8') ?>"
        data-upload-chunk="<?= htmlspecialchars(url('admin/modpacks/upload/chunk'), ENT_QUOTES, 'UTF-8') ?>"
        data-upload-finalize="<?= htmlspecialchars(url('admin/modpacks/upload/finalize'), ENT_QUOTES, 'UTF-8') ?>"
    >
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="staged_upload_id" value="">
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Nom *</label>
            <input type="text" name="name" required class="w-full border border-slate-200 rounded px-3 py-2" value="<?= htmlspecialchars($modpack['name'] ?? '') ?>" />
        </div>
        <div class="mp-admin__split mp-admin__split--2">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Adresse courte dans l’URL</label>
                <input type="text" name="slug" class="w-full border border-slate-200 rounded px-3 py-2" value="<?= htmlspecialchars($modpack['slug'] ?? '') ?>" />
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Version</label>
                <input type="text" name="version" class="w-full border border-slate-200 rounded px-3 py-2" value="<?= htmlspecialchars($modpack['version'] ?? '') ?>" />
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
            <textarea name="description" rows="4" class="w-full border border-slate-200 rounded px-3 py-2"><?= htmlspecialchars($modpack['description'] ?? '') ?></textarea>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Lien externe (optionnel)</label>
            <input type="url" name="url" class="w-full border border-slate-200 rounded px-3 py-2" placeholder="https://…" value="<?= htmlspecialchars($modpack['url'] ?? '') ?>" />
        </div>
        <div>
            <span class="block text-sm font-medium text-slate-700 mb-1">Fichier modpack</span>
            <?php if ($hasFile): ?>
            <p class="mb-2 text-xs text-slate-500">
                Fichier actuel enregistré<?= $sizeLabel !== '' ? ' (' . htmlspecialchars($sizeLabel) . ')' : '' ?>.
                Déposer un nouveau fichier le remplace.
            </p>
            <?php endif; ?>
            <div
                class="mp-admin__dropzone"
                data-modpack-dropzone
                tabindex="0"
                role="button"
                aria-label="Déposer ou choisir un fichier modpack de remplacement"
            >
                <p class="mp-admin__drop-title"><?= $hasFile ? 'Remplacer l’archive' : 'Glisser-déposer l’archive ici' ?></p>
                <p class="mp-admin__drop-sub">ZIP / RAR / 7z · max <?= htmlspecialchars((string) ($limits['max_label'] ?? '2 Go')) ?> · envoi par morceaux</p>
                <p class="mp-admin__file-meta" data-modpack-file-meta hidden></p>
                <button type="button" class="mp-admin__clear" data-modpack-clear-file hidden>Retirer le fichier</button>
                <input
                    class="mp-admin__file-input"
                    type="file"
                    name="modpack_file"
                    accept=".zip,.rar,.7z,application/zip,application/x-rar-compressed,application/x-7z-compressed"
                />
            </div>
            <div class="mp-admin__progress" data-modpack-progress hidden role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                <div class="mp-admin__progress-bar" data-modpack-progress-bar></div>
            </div>
            <p class="mp-admin__status" data-modpack-upload-status hidden></p>
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
        <div class="flex gap-3 pt-2">
            <button type="submit" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded hover:bg-slate-800">Enregistrer</button>
            <a href="<?= url('admin/modpacks') ?>" class="px-4 py-2 border border-slate-200 text-slate-700 text-sm rounded hover:bg-slate-50">Annuler</a>
        </div>
    </form>
</div>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/modpack-admin-upload.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
