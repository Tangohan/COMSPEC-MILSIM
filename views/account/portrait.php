<?php
$user = $user ?? [];
$personnelProfile = $personnelProfile ?? null;
$errors = $errors ?? [];
$success = $success ?? null;
$error = $error ?? null;
$portraitUrl = null;
if (!empty($personnelProfile['character_portrait_path'])) {
    $portraitUrl = url('') . '/' . ltrim($personnelProfile['character_portrait_path'], '/');
}
?>
<div class="max-w-2xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-2">Portrait opérateur</h1>
    <p class="text-slate-600 mb-6">Image in-universe (fiche personnel, ORBAT, briefing). JPG, PNG ou WebP — 2 Mo max. Portrait vertical ou carré conseillé.</p>
    <?php if ($success): ?>
    <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-800 text-sm rounded"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-800 text-sm rounded"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <div class="bg-white border border-slate-200 rounded-lg p-6 flex flex-col sm:flex-row gap-6 items-start">
        <div class="shrink-0">
            <?php if ($portraitUrl): ?>
            <img src="<?= htmlspecialchars($portraitUrl) ?>" alt="Portrait opérateur" class="w-24 h-32 object-cover rounded-lg border-2 border-slate-200">
            <?php else: ?>
            <div class="w-24 h-32 rounded-lg bg-slate-200 flex items-center justify-center text-slate-500">
                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
            </div>
            <?php endif; ?>
        </div>
        <form method="post" action="<?= url('account/portrait') ?>" enctype="multipart/form-data" class="flex-1 space-y-4 w-full">
            <?= \App\Core\Csrf::field() ?>
            <div>
                <label for="portrait" class="block text-sm font-medium text-slate-700 mb-1">Choisir une image</label>
                <input type="file" name="portrait" id="portrait" accept="image/jpeg,image/png,image/webp" class="w-full text-sm text-slate-600 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:bg-slate-100 file:font-semibold file:text-slate-900 hover:file:bg-slate-200">
                <?php if (!empty($errors['portrait'])): foreach ($errors['portrait'] as $e): ?>
                <p class="mt-1 text-sm text-red-600"><?= htmlspecialchars($e) ?></p>
                <?php endforeach; endif; ?>
            </div>
            <button type="submit" class="py-2.5 px-4 bg-slate-900 text-white font-semibold rounded hover:bg-slate-800">Mettre à jour le portrait</button>
        </form>
    </div>
    <p class="mt-6 text-sm text-slate-500"><a href="<?= url('account') ?>" class="underline">Retour à Paramètres</a> — <a href="<?= url('account/image') ?>" class="underline">Photo de compte (avatar)</a></p>
</div>
