<?php
$user = $user ?? [];
$errors = $errors ?? [];
$success = $success ?? null;
$error = $error ?? null;
?>
<div class="max-w-2xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-2">Adresse email</h1>
    <p class="text-slate-600 mb-6">Modifiez l'adresse utilisée pour vous connecter. Une confirmation par mot de passe est requise.</p>
    <?php if ($success): ?>
    <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-800 text-sm rounded"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-800 text-sm rounded"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post" action="<?= url('account/mail') ?>" class="space-y-4 bg-white border border-slate-200 rounded-lg p-6">
        <?= \App\Core\Csrf::field() ?>
        <div>
            <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Nouvelle adresse email</label>
            <input type="email" name="email" id="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required class="w-full px-3 py-2 border border-slate-300 rounded focus:ring-2 focus:ring-slate-900 focus:border-slate-900" autocomplete="email">
            <?php if (!empty($errors['email'])): foreach ($errors['email'] as $e): ?>
            <p class="mt-1 text-sm text-red-600"><?= htmlspecialchars($e) ?></p>
            <?php endforeach; endif; ?>
        </div>
        <div>
            <label for="email_confirmation" class="block text-sm font-medium text-slate-700 mb-1">Confirmer l'adresse email</label>
            <input type="email" name="email_confirmation" id="email_confirmation" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required class="w-full px-3 py-2 border border-slate-300 rounded focus:ring-2 focus:ring-slate-900" autocomplete="email">
            <?php if (!empty($errors['email_confirmation'])): foreach ($errors['email_confirmation'] as $e): ?>
            <p class="mt-1 text-sm text-red-600"><?= htmlspecialchars($e) ?></p>
            <?php endforeach; endif; ?>
        </div>
        <div>
            <label for="password" class="block text-sm font-medium text-slate-700 mb-1">Mot de passe actuel</label>
            <input type="password" name="password" id="password" required class="w-full px-3 py-2 border border-slate-300 rounded focus:ring-2 focus:ring-slate-900" autocomplete="current-password">
            <?php if (!empty($errors['password'])): foreach ($errors['password'] as $e): ?>
            <p class="mt-1 text-sm text-red-600"><?= htmlspecialchars($e) ?></p>
            <?php endforeach; endif; ?>
        </div>
        <button type="submit" class="py-2.5 px-4 bg-slate-900 text-white font-semibold rounded hover:bg-slate-800">Mettre à jour l'email</button>
    </form>
    <p class="mt-6 text-sm text-slate-500"><a href="<?= url('account') ?>" class="underline">Retour à Paramètres</a></p>
</div>
