<?php
$user = $user ?? [];
$errors = $errors ?? [];
$success = $success ?? null;
$error = $error ?? null;
$avatarUrl = function_exists('user_media_public_url')
    ? user_media_public_url($user['avatar_url'] ?? null)
    : (!empty($user['avatar_url']) ? (url('') . '/' . ltrim($user['avatar_url'], '/')) : null);
?>
<div class="max-w-2xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-2">Photo de profil</h1>
    <p class="text-slate-600 mb-6">JPG, PNG ou WebP — 2 Mo max.</p>
    <?php if ($success): ?>
    <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-800 text-sm rounded"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-800 text-sm rounded"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <div class="bg-white border border-slate-200 rounded-lg p-6 flex flex-col sm:flex-row gap-6 items-start">
        <div class="shrink-0 space-y-2">
            <?php if ($avatarUrl): ?>
            <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="Avatar" class="w-24 h-24 rounded-full object-cover border-2 border-slate-200">
            <?php else: ?>
            <div class="w-24 h-24 rounded-full bg-slate-200 flex items-center justify-center text-slate-500 text-2xl font-bold"><?= strtoupper(mb_substr($user['display_name'] ?? $user['email'] ?? '?', 0, 1)) ?></div>
            <?php endif; ?>
            <?php if (!empty($user['id'])): ?>
            <button type="button" data-community-report data-cr-type="profile_picture" data-cr-id="<?= (int) $user['id'] ?>" data-cr-summary="Signalement concernant votre photo de compte (avatar)." class="text-[10px] font-bold uppercase tracking-wide text-rose-700 hover:text-rose-900 border border-rose-200 rounded-lg px-2 py-1 w-full">Signaler cette photo</button>
            <?php endif; ?>
        </div>
        <form method="post" action="<?= url('account/image') ?>" enctype="multipart/form-data" class="flex-1 space-y-4 w-full">
            <?= \App\Core\Csrf::field() ?>
            <div>
                <label for="avatar" class="block text-sm font-medium text-slate-700 mb-1">Choisir une image</label>
                <input type="file" name="avatar" id="avatar" accept="image/jpeg,image/png,image/webp" class="w-full text-sm text-slate-600 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:bg-slate-100 file:font-semibold file:text-slate-900 hover:file:bg-slate-200">
                <?php if (!empty($errors['avatar'])): foreach ($errors['avatar'] as $e): ?>
                <p class="mt-1 text-sm text-red-600"><?= htmlspecialchars($e) ?></p>
                <?php endforeach; endif; ?>
            </div>
            <button type="submit" class="py-2.5 px-4 bg-slate-900 text-white font-semibold rounded hover:bg-slate-800">Mettre à jour la photo</button>
        </form>
    </div>
    <p class="mt-6 text-sm text-slate-500"><a href="<?= url('account') ?>" class="underline">Retour à Paramètres</a> — <a href="<?= url('account/banner') ?>" class="underline">Couverture du menu session</a></p>
</div>
