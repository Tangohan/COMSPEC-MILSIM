<?php
declare(strict_types=1);

/* Page publique absente ou fermée : même réponse dans les deux cas, pour ne rien révéler. */
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>Page introuvable — Athena</title>
    <?php require base_path('views/partials/tailwind_cdn_or_build.php'); ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>body{font-family:'Inter',system-ui,sans-serif}</style>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 flex items-center justify-center px-6">
    <main class="max-w-md text-center">
        <p class="text-[0.65rem] font-black uppercase tracking-[0.35em] text-emerald-400">Erreur 404</p>
        <h1 class="mt-4 text-3xl font-black tracking-tight">Cette page joueur n’existe pas</h1>
        <p class="mt-4 text-sm leading-relaxed text-slate-400">
            L’adresse est peut-être mal saisie, ou le joueur a fermé sa page publique.
        </p>
        <a href="<?= $h(url('/')) ?>" class="mt-8 inline-flex items-center gap-2 rounded-full bg-white px-5 py-2.5 text-sm font-bold text-slate-900 hover:bg-emerald-100">Retour à l’accueil</a>
    </main>
</body>
</html>
