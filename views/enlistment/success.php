<?php
$base = url('');
$communitySlug = $communitySlug ?? null;
$enlistHref = $communitySlug ? $base . '/c/' . rawurlencode((string) $communitySlug) . '/enlistment' : $base . '/enlistment';
$brand = function_exists('email_brand_name') ? email_brand_name() : 'Athena';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidature enregistrée — <?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?></title>
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
            <a class="ds-header__link" href="<?= htmlspecialchars($enlistHref, ENT_QUOTES, 'UTF-8') ?>">Enrôlement</a>
        </div>
    </div>
</header>

<main class="ds-main" id="contenu">
    <p class="ds-kicker">Transmission réussie</p>
    <h1 class="ds-title">Candidature enregistrée</h1>
    <p class="ds-lead">Votre dossier a bien été reçu par la cellule de recrutement. Il sera examiné dans les meilleurs délais.</p>

    <div class="ds-callout" style="margin-top:1.75rem">
        <p class="ds-hint" style="margin-bottom:0.5rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em">Prochaines étapes</p>
        <ul style="margin:0;padding-left:1.1rem;color:var(--ds-text-alt);font-size:0.95rem;line-height:1.55">
            <li>Examen de votre dossier par le commandement</li>
            <li>Contact par e-mail en cas de suite favorable</li>
            <li>Merci de ne pas relancer l’état-major ; les délais peuvent varier</li>
        </ul>
    </div>

    <p class="ds-lead" style="font-size:1rem;margin-top:1.25rem">Nous vous recontacterons à l’adresse e-mail indiquée dans le formulaire.</p>

    <div class="ds-btn-row" style="margin-top:1.75rem">
        <a href="<?= htmlspecialchars($base . '/', ENT_QUOTES, 'UTF-8') ?>" class="ds-btn ds-btn--primary">Retour à l’accueil</a>
    </div>
</main>
</body>
</html>
