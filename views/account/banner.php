<?php
$user = $user ?? [];
$errors = $errors ?? [];
$success = $success ?? null;
$error = $error ?? null;
$bannerUrl = function_exists('user_media_public_url')
    ? user_media_public_url($user['profile_banner_url'] ?? null)
    : null;
?>
<div class="max-w-2xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-2">Couverture du menu session</h1>
    <p class="text-slate-600 mb-6">Image affichée en haut du menu profil (bandeau « Session active »). JPG, PNG ou WebP — 2 Mo max. Format large recommandé.</p>
    <?php if ($success): ?>
    <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-800 text-sm rounded"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-800 text-sm rounded"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <div class="bg-white border border-slate-200 rounded-lg p-6 space-y-6">
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-slate-900">
            <?php if ($bannerUrl): ?>
            <img src="<?= htmlspecialchars($bannerUrl) ?>" alt="Aperçu de la couverture" class="h-28 w-full object-cover sm:h-32">
            <?php else: ?>
            <div class="flex h-28 w-full items-center justify-center bg-gradient-to-br from-emerald-900 via-slate-950 to-black sm:h-32">
                <span class="text-[10px] font-black uppercase tracking-[0.2em] text-white/50">Couverture par défaut</span>
            </div>
            <?php endif; ?>
        </div>
        <form method="post" action="<?= url('account/banner') ?>" enctype="multipart/form-data" class="space-y-4">
            <?= \App\Core\Csrf::field() ?>
            <div>
                <label for="banner" class="block text-sm font-medium text-slate-700 mb-1">Choisir une image</label>
                <input type="file" name="banner" id="banner" accept="image/jpeg,image/png,image/webp" class="w-full text-sm text-slate-600 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:bg-slate-100 file:font-semibold file:text-slate-900 hover:file:bg-slate-200">
                <?php if (!empty($errors['banner'])): foreach ($errors['banner'] as $e): ?>
                <p class="mt-1 text-sm text-red-600"><?= htmlspecialchars($e) ?></p>
                <?php endforeach; endif; ?>
            </div>
            <button type="submit" class="py-2.5 px-4 bg-slate-900 text-white font-semibold rounded hover:bg-slate-800">Mettre à jour la couverture</button>
        </form>
        <?php if ($bannerUrl): ?>
        <form method="post" action="<?= url('account/banner') ?>" onsubmit="return confirm('Retirer la couverture personnalisée et revenir au bandeau par défaut ?');">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="remove_banner" value="1">
            <button type="submit" class="text-sm font-semibold text-rose-700 underline decoration-rose-300 underline-offset-2 hover:text-rose-900">Retirer la couverture</button>
        </form>
        <?php endif; ?>
    </div>
    <p class="mt-6 text-sm text-slate-500">
        <a href="<?= url('account') ?>" class="underline">Retour à Paramètres</a>
        — <a href="<?= url('account/image') ?>" class="underline">Photo de compte</a>
    </p>
</div>
