<?php
declare(strict_types=1);

$title = $title ?? 'Accès indisponible';
$brand = email_brand_name();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#050505">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,600;0,700;0,900;1,900&display=swap">
    <style>
        :root {
            --ink: #f4f4f0;
            --muted: rgba(244, 244, 240, 0.62);
            --dim: rgba(244, 244, 240, 0.38);
            --accent: #34d399;
            --void: #050505;
            --font: Inter, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            min-height: 100%;
            background: var(--void);
            color: var(--ink);
            font-family: var(--font);
            -webkit-font-smoothing: antialiased;
        }
        body {
            min-height: 100svh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2.5rem 1.25rem;
            text-align: center;
        }
        .wrap { max-width: 28rem; }
        .kicker {
            margin: 0;
            font-size: 0.6875rem;
            font-weight: 600;
            letter-spacing: 0.28em;
            text-transform: uppercase;
            color: var(--accent);
        }
        h1 {
            margin: 1.25rem 0 0;
            font-size: clamp(2.5rem, 12vw, 4.5rem);
            font-weight: 900;
            font-style: normal;
            text-transform: uppercase;
            letter-spacing: -0.05em;
            line-height: 0.95;
            color: #fff;
        }
        h1 span { color: var(--accent); }
        p {
            margin: 1.5rem 0 0;
            font-size: 1rem;
            font-weight: 500;
            line-height: 1.65;
            color: var(--muted);
        }
        .dim { color: var(--dim); font-size: 0.875rem; }
    </style>
</head>
<body>
    <main class="wrap">
        <p class="kicker">TTRD.FR</p>
        <h1>Indisponible<span>.</span></h1>
        <p>
            Cette démonstration n’est plus accessible depuis votre connexion.
            La fenêtre d’entrée ou la durée d’accès autorisée est écoulée.
        </p>
        <p class="dim">Contactez directement TTRD.FR si besoin.</p>
    </main>
</body>
</html>
