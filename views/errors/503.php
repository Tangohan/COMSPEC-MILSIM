<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance — <?= htmlspecialchars(config('app.name', 'Athena')) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-900 font-sans flex items-center justify-center min-h-screen">
    <div class="text-center px-6 max-w-md">
        <h1 class="text-3xl font-black text-slate-900 mb-4">Maintenance</h1>
        <p class="text-slate-600 mb-6"><?= nl2br(htmlspecialchars($message ?? 'Maintenance en cours. Merci de réessayer dans quelques minutes.')) ?></p>
        <p class="text-sm text-slate-500">Nous serons de retour très bientôt.</p>
    </div>
</body>
</html>
