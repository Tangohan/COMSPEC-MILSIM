<?php
declare(strict_types=1);
$title = (string) ($title ?? 'Connexion expirée — ATAK');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #0f172a; font-family: -apple-system, system-ui, sans-serif; padding: 1.5rem; }
        .card { width: 100%; max-width: 22rem; background: #fff; border-radius: 1.25rem; padding: 2rem 1.5rem; text-align: center; box-shadow: 0 20px 50px -12px rgba(0,0,0,.4); }
        h1 { font-size: 1.1rem; font-weight: 900; text-transform: uppercase; letter-spacing: .05em; color: #0f172a; margin: 0 0 .5rem; }
        p { font-size: .875rem; color: #475569; line-height: 1.5; margin: 0 0 1.5rem; }
        a { display: inline-block; padding: .85rem 1.5rem; border-radius: .75rem; background: #059669; color: #fff; font-weight: 800; font-size: .8rem; text-transform: uppercase; letter-spacing: .05em; text-decoration: none; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Lien expiré</h1>
        <p>Ce code de connexion a expiré ou n’est plus valide. Générez-en un nouveau depuis la tablette en jeu, ou saisissez un code manuellement.</p>
        <a href="<?= htmlspecialchars(url('atak/connect'), ENT_QUOTES, 'UTF-8') ?>">Saisir un code</a>
    </div>
</body>
</html>
