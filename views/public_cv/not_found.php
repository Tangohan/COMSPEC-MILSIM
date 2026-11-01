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
    <meta name="theme-color" content="#050505">
    <title>Page introuvable — Athena</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,600;0,900;1,900&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html, body { margin: 0; background: #050505; color: #f4f4f0; font-family: Inter, system-ui, -apple-system, 'Segoe UI', sans-serif; -webkit-font-smoothing: antialiased; }
        body { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 48px 16px; background-image: linear-gradient(rgba(244, 244, 240, .035) 1px, transparent 1px), linear-gradient(90deg, rgba(244, 244, 240, .035) 1px, transparent 1px); background-size: 40px 40px; }
        main { max-width: 34rem; text-align: center; }
        .k { margin: 0; font-size: .6875rem; font-weight: 600; letter-spacing: .28em; text-transform: uppercase; color: #34d399; }
        h1 { margin: 16px 0 0; font-size: clamp(2rem, 7vw, 3.25rem); font-weight: 900; font-style: italic; letter-spacing: -.04em; line-height: .95; text-transform: uppercase; }
        p.t { margin: 20px 0 0; font-size: 15px; line-height: 1.6; color: rgba(244, 244, 240, .6); }
        a { display: inline-flex; align-items: center; justify-content: center; height: 44px; margin-top: 32px; padding: 0 20px; background: #34d399; color: #052e1c; font-size: 11px; font-weight: 900; letter-spacing: .22em; text-transform: uppercase; text-decoration: none; transition: background-color .14s; }
        a:hover { background: #6ee7b7; }
        a:focus-visible { outline: 2px solid #34d399; outline-offset: 3px; }
    </style>
</head>
<body>
    <main>
        <p class="k">Erreur 404</p>
        <h1>Cette page joueur n’existe pas</h1>
        <p class="t">L’adresse est peut-être mal saisie, ou le joueur a fermé sa page publique.</p>
        <a href="<?= $h(url('/')) ?>">Retour à l’accueil</a>
    </main>
</body>
</html>
