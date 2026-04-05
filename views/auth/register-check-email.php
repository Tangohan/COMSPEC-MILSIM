<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Confirmez votre e-mail') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-white rounded-xl shadow border border-slate-200 p-8 text-center">
        <h1 class="text-xl font-bold text-slate-900 mb-2">Presque terminé</h1>
        <p class="text-slate-600 text-sm mb-4">Un lien de confirmation a été envoyé à</p>
        <p class="font-mono text-sm font-semibold text-slate-800 break-all"><?= htmlspecialchars((string) ($email ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
        <p class="text-slate-500 text-sm mt-4">Cliquez sur le lien dans l’e-mail (valide 15 min) pour activer votre compte.</p>
        <a href="<?= htmlspecialchars(url('login'), ENT_QUOTES, 'UTF-8') ?>" class="inline-block mt-6 text-sm font-semibold text-blue-700 hover:underline">Retour à la connexion</a>
    </div>
</body>
</html>
