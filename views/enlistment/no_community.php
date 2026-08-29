<?php
$base = url('');
$loginUrl = $loginUrl ?? url('login');
$joinUrl = $joinUrl ?? url('join');
$brand = function_exists('email_brand_name') ? email_brand_name() : 'Athena';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrôlement — Aucune organisation</title>
    <?php $tailwindBaseUrl = $base; require base_path('views/partials/tailwind_cdn_or_build.php'); ?>
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="<?= htmlspecialchars(asset_url('assets/css/dsfr-service.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php $GLOBALS['__dsfr_service_css'] = true; ?>
</head>
<body class="ds-page">
<a class="ds-skip" href="#contenu">Aller au contenu</a>
<header class="ds-header">
    <div class="ds-header__band" aria-hidden="true"></div>
    <div class="ds-header__inner">
        <a href="<?= htmlspecialchars($base . '/', ENT_QUOTES, 'UTF-8') ?>" class="ds-header__brand">
            <span class="ds-header__service"><?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?></span>
            <span class="ds-header__tagline">Recrutement</span>
        </a>
        <div class="ds-header__tools">
            <a class="ds-header__link" href="<?= htmlspecialchars($base . '/', ENT_QUOTES, 'UTF-8') ?>">Accueil</a>
        </div>
    </div>
</header>

<main class="ds-main" id="contenu">
    <p class="ds-kicker">Contexte manquant</p>
    <h1 class="ds-title">Aucune unité sélectionnée</h1>
    <p class="ds-lead">
        Ce portail d’enrôlement est lié à une communauté précise. Sans organisation cible, le dossier ne peut pas être ouvert.
    </p>

    <div class="ds-callout" style="margin-top:1.75rem">
        <p class="ds-hint" style="margin-bottom:0.5rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em">Comment continuer</p>
        <ol style="margin:0;padding-left:1.1rem;color:var(--ds-text-alt);font-size:0.95rem;line-height:1.55">
            <li>Utilisez le lien d’invitation envoyé par votre organisation.</li>
            <li>Sinon, contactez votre unité pour obtenir le bon lien.</li>
            <li>Si vous avez déjà un compte, connectez-vous pour choisir votre communauté.</li>
        </ol>
    </div>

    <div class="ds-btn-row" style="margin-top:1.75rem">
        <a href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>" class="ds-btn ds-btn--primary">Connexion</a>
        <a href="<?= htmlspecialchars($joinUrl, ENT_QUOTES, 'UTF-8') ?>" class="ds-btn ds-btn--secondary">Code d’invitation</a>
        <a href="<?= htmlspecialchars($base . '/', ENT_QUOTES, 'UTF-8') ?>" class="ds-btn ds-btn--secondary">Accueil</a>
    </div>
</main>
</body>
</html>
