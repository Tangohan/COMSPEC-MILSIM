<?php
declare(strict_types=1);
$title = (string) ($title ?? 'Connexion téléphone — ATAK');
$err = \App\Core\Session::getFlash('error');
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
        input { width: 100%; box-sizing: border-box; font-size: 1.5rem; text-align: center; letter-spacing: .2em; text-transform: uppercase; padding: .75rem; border-radius: .75rem; border: 2px solid #cbd5e1; margin-bottom: 1rem; }
        button { width: 100%; padding: .85rem; border: 0; border-radius: .75rem; background: #059669; color: #fff; font-weight: 800; font-size: .8rem; text-transform: uppercase; letter-spacing: .05em; cursor: pointer; }
        .err { background: #fee2e2; color: #991b1b; border-radius: .6rem; padding: .6rem .8rem; font-size: .8rem; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Connexion téléphone</h1>
        <p>Saisissez le code affiché sur la tablette en jeu.</p>
        <?php if ($err): ?><div class="err"><?= htmlspecialchars((string) $err, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <form method="post" action="<?= htmlspecialchars(url('atak/connect/code'), ENT_QUOTES, 'UTF-8') ?>">
            <?= \App\Core\Csrf::field() ?>
            <input type="text" name="code" maxlength="8" autocapitalize="characters" autocomplete="off" placeholder="XXXXXX" required autofocus>
            <button type="submit">Se connecter</button>
        </form>
    </div>
</body>
</html>
