<?php
$error = \App\Core\Session::getFlash('error');
?>
<div class="max-w-md mx-auto px-6 py-16">
    <h1 class="text-2xl font-black text-slate-900 mb-2">Connexion</h1>
    <p class="text-slate-600 mb-8">Accès au portail Athena.</p>
    <?php if ($error): ?>
    <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-800 text-sm rounded"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post" action="<?= url('login') ?>" class="space-y-4">
        <?= \App\Core\Csrf::field() ?>
        <div>
            <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Email</label>
            <input type="email" name="email" id="email" required class="w-full px-3 py-2 border border-slate-300 rounded focus:ring-2 focus:ring-slate-900 focus:border-slate-900" autocomplete="email">
        </div>
        <div>
            <label for="password" class="block text-sm font-medium text-slate-700 mb-1">Mot de passe</label>
            <input type="password" name="password" id="password" required class="w-full px-3 py-2 border border-slate-300 rounded focus:ring-2 focus:ring-slate-900 focus:border-slate-900" autocomplete="current-password">
        </div>
        <button type="submit" class="w-full py-2.5 bg-slate-900 text-white font-semibold rounded hover:bg-slate-800">Se connecter</button>
    </form>
    <p class="mt-6 text-center text-sm text-slate-500">
        <a href="<?= url('') ?>" class="underline">Retour à l'accueil</a>
    </p>
</div>
