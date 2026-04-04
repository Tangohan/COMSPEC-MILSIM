<?php
$errors = $errors ?? [];
$success = $success ?? null;
$error = $error ?? null;
?>
<div class="max-w-2xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-2">Mot de passe</h1>
    <p class="text-slate-600 mb-6">Changer votre mot de passe. Minimum 8 caractères.</p>
    <?php if ($success): ?>
    <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-800 text-sm rounded"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-800 text-sm rounded"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post" action="<?= url('account/password') ?>" class="space-y-4 bg-white border border-slate-200 rounded-lg p-6">
        <?= \App\Core\Csrf::field() ?>
        <div>
            <label for="current_password" class="block text-sm font-medium text-slate-700 mb-1">Mot de passe actuel</label>
            <input type="password" name="current_password" id="current_password" required class="w-full px-3 py-2 border border-slate-300 rounded focus:ring-2 focus:ring-slate-900" autocomplete="current-password">
            <?php if (!empty($errors['current_password'])): foreach ($errors['current_password'] as $e): ?>
            <p class="mt-1 text-sm text-red-600"><?= htmlspecialchars($e) ?></p>
            <?php endforeach; endif; ?>
        </div>
        <div>
            <label for="new_password" class="block text-sm font-medium text-slate-700 mb-1">Nouveau mot de passe</label>
            <input type="password" name="new_password" id="new_password" required minlength="8" class="w-full px-3 py-2 border border-slate-300 rounded focus:ring-2 focus:ring-slate-900" autocomplete="new-password">
            <?php if (!empty($errors['new_password'])): foreach ($errors['new_password'] as $e): ?>
            <p class="mt-1 text-sm text-red-600"><?= htmlspecialchars($e) ?></p>
            <?php endforeach; endif; ?>
        </div>
        <div>
            <label for="new_password_confirmation" class="block text-sm font-medium text-slate-700 mb-1">Confirmer le nouveau mot de passe</label>
            <input type="password" name="new_password_confirmation" id="new_password_confirmation" required minlength="8" class="w-full px-3 py-2 border border-slate-300 rounded focus:ring-2 focus:ring-slate-900" autocomplete="new-password">
            <?php if (!empty($errors['new_password_confirmation'])): foreach ($errors['new_password_confirmation'] as $e): ?>
            <p class="mt-1 text-sm text-red-600"><?= htmlspecialchars($e) ?></p>
            <?php endforeach; endif; ?>
        </div>
        <button type="submit" class="py-2.5 px-4 bg-slate-900 text-white font-semibold rounded hover:bg-slate-800">Changer le mot de passe</button>
    </form>
    <p class="mt-6 text-sm text-slate-500"><a href="<?= url('account') ?>" class="underline">Retour à Paramètres</a> · <a href="<?= url('forgot-password') ?>" class="underline">Mot de passe oublié ?</a></p>
</div>
