<?php
declare(strict_types=1);
$title = $title ?? 'Mur introuvable';
$baseUrl = url('');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> — Athéna</title>
    <meta name="robots" content="noindex,nofollow">
    <link rel="stylesheet" href="<?= htmlspecialchars(asset_url('assets/css/tailwind.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link href="<?= htmlspecialchars(asset_url('assets/css/operational-board.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
</head>
<body class="ops-pub ops-pub--missing">
<header class="ops-pub__top">
    <div class="ops-pub__top-inner">
        <p class="ops-pub__brand">Athéna</p>
        <p class="ops-pub__readonly">Lecture seule</p>
    </div>
</header>
<main class="ops-pub__main" style="max-width:32rem;margin:4rem auto;text-align:center">
    <h1 class="ops-pub__title" style="font-size:1.5rem">Ce mur n’est plus disponible</h1>
    <p class="ops-pub__lead" style="margin:1rem auto">Le lien a peut‑être été renouvelé ou désactivé par l’état-major. Demandez un nouveau lien de consultation.</p>
    <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>" class="ops-board__btn ops-board__btn--solid" style="display:inline-flex;margin-top:1.5rem;background:#064e3b;color:#ecfdf5">Retour à Athéna</a>
</main>
</body>
</html>
