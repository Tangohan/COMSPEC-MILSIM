<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'Inscription') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <h1 class="text-xl font-bold mb-6">Créer un compte</h1>
        <?php $err = \App\Core\Session::getFlash('error'); $ok = \App\Core\Session::getFlash('success'); ?>
        <?php if ($err): ?><p class="text-red-400 text-sm mb-4"><?= htmlspecialchars($err) ?></p><?php endif; ?>
        <?php if ($ok): ?><p class="text-emerald-400 text-sm mb-4"><?= htmlspecialchars($ok) ?></p><?php endif; ?>
        <form method="post" action="<?= url('register') ?>" class="space-y-4">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
            <div>
                <label class="block text-xs text-slate-400 mb-1">Email</label>
                <input type="email" name="email" required class="w-full rounded bg-slate-900 border border-slate-700 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1">Nom affiché (optionnel)</label>
                <input type="text" name="display_name" class="w-full rounded bg-slate-900 border border-slate-700 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1">Mot de passe (min. 8 caractères)</label>
                <input type="password" name="password" required minlength="8" class="w-full rounded bg-slate-900 border border-slate-700 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1">Confirmation</label>
                <input type="password" name="password_confirmation" required minlength="8" class="w-full rounded bg-slate-900 border border-slate-700 px-3 py-2 text-sm">
            </div>
            <button type="submit" class="w-full py-2 rounded bg-emerald-600 hover:bg-emerald-500 font-semibold text-sm">S’inscrire</button>
        </form>
        <p class="mt-4 text-sm text-slate-500"><a href="<?= url('login') ?>" class="underline">Déjà un compte ?</a></p>
    </div>
</body>
</html>
