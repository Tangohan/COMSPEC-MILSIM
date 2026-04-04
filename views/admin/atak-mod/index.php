<?php
$hasMod = $hasMod ?? false;
$success = $success ?? null;
$error = $error ?? null;
$errors = $errors ?? [];
$baseUrl = url('');
?>
<div class="max-w-2xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-2">Mod ATAK — Administration</h1>
    <p class="text-sm text-slate-600 mb-6">Upload et vérification du mod dédié COMSPEC Overwatch (zip). Une fois validé, le mod est proposé au téléchargement sur la page ATAK.</p>

    <?php if ($success): ?>
        <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-800 text-sm rounded-lg"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-800 text-sm rounded-lg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <ul class="mb-4 list-disc list-inside text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg p-3">
            <?php foreach ($errors as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ($hasMod): ?>
    <div class="border border-slate-200 rounded-lg p-4 bg-slate-50/50 mb-6">
        <h2 class="text-sm font-bold text-slate-800 mb-2">Mod actuel</h2>
        <p class="text-sm text-slate-600 mb-3">Un mod est déjà enregistré. Il est disponible au téléchargement sur la page ATAK (section « Configuration pour le jeu »).</p>
        <form action="<?= $baseUrl ?>/admin/atak-mod/delete" method="post" class="inline" onsubmit="return confirm('Supprimer le mod actuel ?');">
            <?= \App\Core\Csrf::field() ?>
            <button type="submit" class="px-3 py-1.5 text-sm border border-red-200 text-red-700 rounded hover:bg-red-50">Supprimer le mod</button>
        </form>
    </div>
    <?php endif; ?>

    <div class="border border-slate-200 rounded-lg p-6 bg-white shadow-sm">
        <h2 class="text-lg font-bold text-slate-800 mb-3">Déposer une nouvelle version du mod</h2>
        <p class="text-sm text-slate-600 mb-4">Archive <strong>.zip</strong> contenant la structure du mod Arma (fichier <code class="bg-slate-100 px-1 rounded">mod.cpp</code> et dossier <code class="bg-slate-100 px-1 rounded">addons</code>). Taille max. 50 Mo.</p>
        <form action="<?= $baseUrl ?>/admin/atak-mod/upload" method="post" enctype="multipart/form-data" class="space-y-4">
            <?= \App\Core\Csrf::field() ?>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Fichier ZIP du mod</label>
                <input type="file" name="mod_zip" accept=".zip" required class="block w-full text-sm text-slate-600 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:bg-slate-100 file:text-slate-800 hover:file:bg-slate-200" />
            </div>
            <div class="flex flex-wrap gap-3">
                <button type="submit" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded hover:bg-slate-800">Vérifier et enregistrer</button>
                <a href="<?= $baseUrl ?>/admin/atak-config" class="px-4 py-2 border border-slate-200 text-slate-700 text-sm rounded hover:bg-slate-50">Config ATAK</a>
                <a href="<?= $baseUrl ?>/atak" class="px-4 py-2 border border-slate-200 text-slate-700 text-sm rounded hover:bg-slate-50">Ouvrir ATAK</a>
                <a href="<?= $baseUrl ?>/admin" class="px-4 py-2 border border-slate-200 text-slate-700 text-sm rounded hover:bg-slate-50">Retour admin</a>
            </div>
        </form>
    </div>
</div>
