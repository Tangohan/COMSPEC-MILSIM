<?php
declare(strict_types=1);
$title = (string) ($title ?? 'Connexion expirée — Athena ATAK');
$entryUrlLabel = (string) ($entryUrlLabel ?? 'athena.ttrd.fr/connect');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0b1220">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.25rem;
            padding-bottom: calc(1.25rem + env(safe-area-inset-bottom));
            background:
                radial-gradient(900px 420px at 12% -8%, #1e293b 0%, transparent 55%),
                linear-gradient(165deg, #0b1220 0%, #020617 100%);
            font-family: "Segoe UI", system-ui, -apple-system, sans-serif;
        }
        .card {
            width: 100%;
            max-width: 22.5rem;
            background: #fff;
            border-radius: 1.25rem;
            padding: 2rem 1.5rem;
            text-align: center;
            box-shadow: 0 22px 50px -18px rgba(0, 0, 0, .55);
        }
        .eyebrow {
            margin: 0 0 .45rem;
            font-size: .62rem;
            font-weight: 800;
            letter-spacing: .22em;
            text-transform: uppercase;
            color: #059669;
        }
        h1 {
            font-size: 1.1rem;
            font-weight: 900;
            letter-spacing: .03em;
            color: #0f172a;
            margin: 0 0 .55rem;
        }
        p {
            font-size: .875rem;
            color: #475569;
            line-height: 1.5;
            margin: 0 0 1.35rem;
        }
        a {
            display: inline-block;
            padding: .85rem 1.5rem;
            border-radius: .85rem;
            background: #059669;
            color: #fff;
            font-weight: 800;
            font-size: .8rem;
            letter-spacing: .05em;
            text-transform: uppercase;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="card">
        <p class="eyebrow">Athena · ATAK</p>
        <h1>Lien expiré</h1>
        <p>Ce code de connexion a expiré ou n’est plus valide. Générez-en un nouveau depuis Athena sur l’ordinateur (bouton <strong>Téléphone</strong>), puis saisissez-le sur <?= htmlspecialchars($entryUrlLabel, ENT_QUOTES, 'UTF-8') ?>.</p>
        <a href="<?= htmlspecialchars(url('connect'), ENT_QUOTES, 'UTF-8') ?>">Saisir un code</a>
    </div>
</body>
</html>
