<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'Rejoindre') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <h1 class="text-xl font-bold mb-2">Rejoindre une communauté</h1>
        <p class="text-sm text-slate-400 mb-6">Saisissez le code communauté (ex. <code class="text-emerald-400">UNIT-ALPHA</code>) pour être redirigé vers la page ou l’inscription.</p>
        <?php $err = \App\Core\Session::getFlash('error'); ?>
        <?php if ($err): ?><p class="text-red-400 text-sm mb-4"><?= htmlspecialchars($err) ?></p><?php endif; ?>
        <form method="post" action="<?= url('community/resolve-code') ?>" class="space-y-4">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
            <div>
                <label class="block text-xs text-slate-400 mb-1">Code communauté</label>
                <input type="text" name="community_code" value="<?= htmlspecialchars($prefill_code ?? '') ?>" required minlength="3" maxlength="64" class="w-full rounded bg-slate-900 border border-slate-700 px-3 py-2 text-sm uppercase" placeholder="UNIT-2026">
            </div>
            <button type="submit" class="w-full py-2 rounded bg-emerald-600 hover:bg-emerald-500 font-semibold text-sm">Continuer</button>
        </form>
        <p class="mt-4 text-sm text-slate-500"><a href="<?= url('') ?>" class="underline">Accueil</a> · <a href="<?= url('register') ?>" class="underline">Créer un compte</a></p>
    </div>
</body>
</html>
