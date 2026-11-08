<?php
declare(strict_types=1);

$base = url('');
$title = $title ?? 'Rejoindre';
$prefillCode = (string) ($prefill_code ?? '');
$err = \App\Core\Session::getFlash('error');
$cssHref = url('assets/css/community-public-dark.css') . '?v=' . rawurlencode((string) (@filemtime(base_path('public/assets/css/community-public-dark.css')) ?: 1));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars((string) $title) ?> — Athena</title>
    <meta name="robots" content="noindex,follow">
    <meta name="color-scheme" content="dark">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="<?= htmlspecialchars($cssHref, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <style>body { margin: 0; background: #020617; }</style>
</head>
<body>
<main class="cp cp--join">
    <div class="cp__join">
        <p class="cp__chip cp__chip--open" style="display:inline-flex">Accès communauté</p>
        <h1 class="cp__title">Rejoindre avec un code</h1>
        <p class="cp__lead">Saisissez le code communauté donné par votre équipe (invitation, brief, message). Vous passez ensuite à la bonne étape : connexion ou création de compte.</p>

        <?php if ($err): ?>
            <p class="cp__flash cp__flash--err" role="alert" style="margin-top:1rem"><?= htmlspecialchars((string) $err) ?></p>
        <?php endif; ?>

        <form method="post" action="<?= htmlspecialchars(url('community/resolve-code'), ENT_QUOTES, 'UTF-8') ?>" class="cp__form">
            <?= \App\Core\Csrf::field() ?>
            <label class="cp__label" for="community_code">Code communauté</label>
            <input
                id="community_code"
                class="cp__input"
                type="text"
                name="community_code"
                value="<?= htmlspecialchars($prefillCode, ENT_QUOTES, 'UTF-8') ?>"
                required
                minlength="3"
                maxlength="64"
                placeholder="Saisissez votre code"
                autocomplete="off"
                autocapitalize="characters"
                enterkeyhint="go">
            <button type="submit" class="cp__btn cp__btn--primary">Continuer</button>
        </form>

        <div class="cp__links">
            <a href="<?= htmlspecialchars(url('communities'), ENT_QUOTES, 'UTF-8') ?>" class="cp__btn cp__btn--ghost">Voir les communautés</a>
            <a href="<?= htmlspecialchars(url('register'), ENT_QUOTES, 'UTF-8') ?>" class="cp__btn cp__btn--ghost">Créer un compte</a>
            <a href="<?= htmlspecialchars(url('login'), ENT_QUOTES, 'UTF-8') ?>" class="cp__btn cp__btn--ghost">Connexion</a>
        </div>
        <p class="cp__foot"><a href="<?= htmlspecialchars($base === '' ? '/' : $base, ENT_QUOTES, 'UTF-8') ?>">Retour à l’accueil</a></p>
    </div>
</main>
</body>
</html>
