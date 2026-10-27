<?php
declare(strict_types=1);
/**
 * <head> commun des pages d’accès (connexion, code de vérification, mot de passe, communauté).
 * Attend : $authTitle (titre de l’onglet). Pose le mode nuit avant le premier rendu.
 */
$authBrand = function_exists('email_brand_name') ? email_brand_name() : 'Athena';
$authBase = url('');
?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string) $authTitle, ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($authBrand, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="theme-color" content="#0b3d38">
    <meta name="robots" content="noindex,nofollow">
    <script>
    // Mode nuit : même préférence que le reste du site (localStorage « athena.bo.theme », sinon le système).
    (function () {
        var pref = null;
        try { pref = localStorage.getItem('athena.bo.theme'); } catch (e) {}
        var dark = pref === 'dark' || (pref !== 'light' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.setAttribute('data-bo-theme', dark ? 'dark' : 'light');
    })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&family=Source+Sans+3:ital,wght@0,400;0,500;0,700;1,400&display=swap" rel="stylesheet">
    <?php $tailwindBaseUrl = $authBase; require base_path('views/partials/tailwind_cdn_or_build.php'); ?>
    <link href="<?= htmlspecialchars(asset_url('assets/css/athena-header.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <link href="<?= htmlspecialchars(asset_url('assets/css/dsfr-service.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <link href="<?= htmlspecialchars(asset_url('assets/css/auth-register.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <link href="<?= htmlspecialchars(asset_url('assets/css/auth-pages.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php $GLOBALS['__dsfr_service_css'] = true; ?>
