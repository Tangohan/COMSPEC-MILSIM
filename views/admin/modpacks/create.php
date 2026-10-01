<?php
$name = $_POST['name'] ?? '';
$slug = $_POST['slug'] ?? '';
$version = $_POST['version'] ?? '';
$description = $_POST['description'] ?? '';
$urlValue = $_POST['url'] ?? '';
$limits = is_array($modpackUploadLimits ?? null) ? $modpackUploadLimits : [
    'max_bytes' => 2 * 1024 * 1024 * 1024,
    'chunk_bytes' => 8 * 1024 * 1024,
    'max_label' => '2 Go',
    'extensions' => ['zip', 'rar', '7z'],
];
$limitsJson = htmlspecialchars(json_encode($limits, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}', ENT_QUOTES, 'UTF-8');
?>
<link rel="stylesheet" href="<?= htmlspecialchars(asset_url('assets/css/modpack-admin.css'), ENT_QUOTES, 'UTF-8') ?>">
<div class="mp-admin max-w-2xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-2">Nouveau modpack</h1>
    <p class="mp-admin__lead">
        Déposez une archive (ZIP, RAR, 7z) jusqu’à <?= htmlspecialchars((string) ($limits['max_label'] ?? '2 Go')) ?>.
        Les gros fichiers sont envoyés par morceaux avec une barre de progression.
        Vous pouvez aussi indiquer uniquement un lien externe (Steam Workshop, miroir…).
    </p>
    <?php if (\App\Core\Session::get('error')): ?>
    <p class="mb-4 text-sm text-red-600"><?= htmlspecialchars(\App\Core\Session::get('error')) ?></p>
    <?php \App\Core\Session::forget('error'); endif; ?>
    <div class="mp-admin__callout" role="note">
        Astuce : laissez l’onglet ouvert jusqu’à la fin de l’envoi. Ne rechargez pas la page pendant la progression.
    </div>
    <form
        action="<?= url('admin/modpacks/store') ?>"
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
            <input type="text" name="name" required class="w-full border border-slate-200 rounded px-3 py-2" value="<?= htmlspecialchars($name) ?>" />
        </div>
        <div class="mp-admin__split mp-admin__split--2">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Adresse courte (optionnel)</label>
                <input type="text" name="slug" class="w-full border border-slate-200 rounded px-3 py-2" placeholder="ex: modpack-principal" value="<?= htmlspecialchars($slug) ?>" />
                <p class="mp-admin__hint">Générée automatiquement à partir du nom si vide.</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Version</label>
                <input type="text" name="version" class="w-full border border-slate-200 rounded px-3 py-2" placeholder="V1.0.0-STABLE" value="<?= htmlspecialchars($version) ?>" />
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
            <textarea name="description" rows="4" class="w-full border border-slate-200 rounded px-3 py-2"><?= htmlspecialchars($description) ?></textarea>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Lien externe (optionnel)</label>
            <input type="url" name="url" class="w-full border border-slate-200 rounded px-3 py-2" placeholder="https://…" value="<?= htmlspecialchars($urlValue) ?>" />
            <p class="mp-admin__hint">Utile pour un Workshop Steam ou un miroir. Peut compléter ou remplacer le fichier local.</p>
        </div>
        <div>
            <span class="block text-sm font-medium text-slate-700 mb-1">Fichier modpack</span>
            <div
                class="mp-admin__dropzone"
                data-modpack-dropzone
                tabindex="0"
                role="button"
                aria-label="Déposer ou choisir un fichier modpack"
            >
                <p class="mp-admin__drop-title">Glisser-déposer l’archive ici</p>
                <p class="mp-admin__drop-sub">ou cliquer pour parcourir — ZIP / RAR / 7z · max <?= htmlspecialchars((string) ($limits['max_label'] ?? '2 Go')) ?></p>
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
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Images (JPG, PNG, WebP — max 5 Mo chacune)</label>
            <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple class="w-full border border-slate-200 rounded px-3 py-2" />
        </div>
        <div class="flex gap-3 pt-2">
            <button type="submit" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded hover:bg-slate-800">Créer</button>
            <a href="<?= url('admin/modpacks') ?>" class="px-4 py-2 border border-slate-200 text-slate-700 text-sm rounded hover:bg-slate-50">Annuler</a>
        </div>
    </form>
</div>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/modpack-admin-upload.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
