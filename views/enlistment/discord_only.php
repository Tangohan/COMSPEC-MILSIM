<?php
declare(strict_types=1);
/**
 * Enrôlement d’une communauté « Bureau des effectifs » ou « Carte ATAK » : pas de candidature
 * sur le site, on rejoint la communauté par son Discord.
 *
 * @var string $tenantName
 * @var string $discordUrl
 * @var string $contactEmail
 * @var string $publicPageUrl
 */
$base = url('');
$brand = function_exists('email_brand_name') ? email_brand_name() : 'Athena';
$tenantName = (string) ($tenantName ?? 'Communauté');
$discordUrl = (string) ($discordUrl ?? '');
$contactEmail = (string) ($contactEmail ?? '');
$publicPageUrl = (string) ($publicPageUrl ?? url('communities'));
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rejoindre <?= $h($tenantName) ?> — <?= $h($brand) ?></title>
    <?php $tailwindBaseUrl = $base; require base_path('views/partials/tailwind_cdn_or_build.php'); ?>
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="<?= $h(asset_url('assets/css/dsfr-service.css')) ?>" rel="stylesheet">
    <?php $GLOBALS['__dsfr_service_css'] = true; ?>
</head>
<body class="ds-page">
<a class="ds-skip" href="#contenu">Aller au contenu</a>
<header class="ds-header">
    <div class="ds-header__band" aria-hidden="true"></div>
    <div class="ds-header__inner">
        <a href="<?= $h($base . '/') ?>" class="ds-header__brand">
            <span class="ds-header__service"><?= $h($brand) ?></span>
            <span class="ds-header__tagline">Recrutement</span>
        </a>
        <div class="ds-header__tools">
            <a class="ds-header__link" href="<?= $h($publicPageUrl) ?>">Page de la communauté</a>
        </div>
    </div>
</header>

<main class="ds-main" id="contenu">
    <p class="ds-kicker">Recrutement sur Discord</p>
    <h1 class="ds-title">Rejoindre <?= $h($tenantName) ?></h1>

    <div class="ds-callout" style="margin-top:1.25rem">
        <p style="margin:0 0 .75rem;font-size:1.05rem;line-height:1.55">
            <strong>Pas de formulaire de candidature sur ce site.</strong>
            Tout se passe sur son serveur Discord : présentez-vous dans le salon d’accueil, l’état-major vous répondra là-bas.
        </p>
        <ul style="margin:0;padding-left:1.1rem;color:var(--ds-text-alt);font-size:0.95rem;line-height:1.6">
            <li>Vous n’avez pas besoin de créer de compte ici.</li>
            <li>Une fois accepté, l’état-major crée votre fiche dans son registre des effectifs.</li>
        </ul>
    </div>

    <div style="display:flex;flex-wrap:wrap;gap:.75rem;margin-top:1.5rem">
        <?php if ($discordUrl !== ''): ?>
        <a class="ds-btn ds-btn--primary" href="<?= $h($discordUrl) ?>" target="_blank" rel="noopener noreferrer">Rejoindre le Discord</a>
        <?php elseif ($contactEmail !== ''): ?>
        <a class="ds-btn ds-btn--primary" href="mailto:<?= $h($contactEmail) ?>">Écrire à l’état-major</a>
        <?php else: ?>
        <p class="ds-hint" style="margin:0">Le lien du Discord n’est pas encore publié. Revenez bientôt ou contactez la communauté depuis sa page.</p>
        <?php endif; ?>
        <a class="ds-btn ds-btn--secondary" href="<?= $h($publicPageUrl) ?>">Retour à la page de la communauté</a>
    </div>
</main>
</body>
</html>
