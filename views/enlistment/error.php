<?php
declare(strict_types=1);
$base = url('');
$message = $message ?? 'Une erreur est survenue lors de la soumission de votre candidature.';
$enlistmentRetryUrl = $enlistmentRetryUrl ?? url('enlistment');
$errorContext = $errorContext ?? null;
$isPortalSuspended = $errorContext === 'portal_access_suspended';
$brand = function_exists('email_brand_name') ? email_brand_name() : 'Athena';
$pageTitle = $isPortalSuspended ? 'Suivi en ligne indisponible' : 'Erreur de soumission';
$heading = $isPortalSuspended ? 'Accès au suivi suspendu' : 'Erreur';
$kicker = $isPortalSuspended ? 'Suivi candidature' : 'Soumission interrompue';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?></title>
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
            <a class="ds-header__link" href="<?= htmlspecialchars($enlistmentRetryUrl, ENT_QUOTES, 'UTF-8') ?>">Enrôlement</a>
        </div>
    </div>
</header>

<main class="ds-main" id="contenu">
    <p class="ds-kicker"><?= htmlspecialchars($kicker, ENT_QUOTES, 'UTF-8') ?></p>
    <h1 class="ds-title"><?= htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') ?></h1>

    <div class="ds-alert-stack">
        <?php $flash_variant = $isPortalSuspended ? 'warning' : 'error'; $flash_message = (string) $message; $flash_margin_class = ''; require base_path('views/partials/flash_message.php'); ?>
    </div>

    <div class="ds-callout" style="margin-top:1.5rem">
        <p class="ds-hint" style="margin-bottom:0.5rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em">Que faire&nbsp;?</p>
        <?php if ($isPortalSuspended): ?>
        <ul style="margin:0;padding-left:1.1rem;color:var(--ds-text-alt);font-size:0.95rem;line-height:1.55">
            <li>Contactez l’équipe recrutement par un moyen déjà utilisé (courriel, forum, etc.).</li>
            <li>Indiquez que le lien de suivi ne s’ouvre plus : ils peuvent rétablir l’accès depuis l’administration.</li>
            <li>Si le lien avait une date limite, un nouveau lien pourra vous être envoyé.</li>
        </ul>
        <?php else: ?>
        <ul style="margin:0;padding-left:1.1rem;color:var(--ds-text-alt);font-size:0.95rem;line-height:1.55">
            <li>Vérifiez que la case « absence d’IA » est cochée si vous renvoyez le formulaire.</li>
            <li>Rechargez la page et remplissez à nouveau le formulaire.</li>
            <li>En cas de persistance, contactez le support indiqué sur la page d’accueil.</li>
        </ul>
        <?php endif; ?>
    </div>

    <div class="ds-btn-row" style="margin-top:1.75rem">
        <?php if (!$isPortalSuspended): ?>
        <a href="<?= htmlspecialchars($enlistmentRetryUrl, ENT_QUOTES, 'UTF-8') ?>" class="ds-btn ds-btn--primary">Réessayer le formulaire</a>
        <?php endif; ?>
        <a href="<?= htmlspecialchars($base . '/', ENT_QUOTES, 'UTF-8') ?>" class="ds-btn ds-btn--secondary">Retour à l’accueil</a>
    </div>
</main>
</body>
</html>
