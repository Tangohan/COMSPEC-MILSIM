<?php

declare(strict_types=1);

/**
 * Gabarit des outils opérateur : le même que le tableau de bord (views/dashboard.php), pour que /operateur et
 * /operateur/identite appartiennent visuellement au site : mêmes feuilles de style, en-tête Athena, rail de gauche,
 * zones « dash-zone », tuiles « dash-quick », cartes « cc-card », boutons « cc-btn », pied de page.
 * Le contenu est la vue $content (operateur.hub ou operateur.identite).
 *
 * @var string $content
 * @var string $title
 */
$base = url('');
$htmlLang = function_exists('html_lang') ? html_lang() : 'fr';
$pageTitle = (string) ($title ?? 'Outils opérateur') . ' — Athena';
$css = static function (string $file): string {
    return is_file(base_path('public/assets/css/' . $file))
        ? '<link href="' . htmlspecialchars(asset_url('assets/css/' . $file), ENT_QUOTES, 'UTF-8') . '" rel="stylesheet">' . "\n    "
        : '';
};
$js = static function (string $file): string {
    return is_file(base_path('public/assets/js/' . $file))
        ? '<script defer src="' . htmlspecialchars(asset_url('assets/js/' . $file), ENT_QUOTES, 'UTF-8') . '"></script>' . "\n"
        : '';
};
$contentPath = base_path('views/' . str_replace('.', '/', (string) ($content ?? 'operateur.hub')) . '.php');
$athena_header_section = (string) ($athena_header_section ?? 'Outils opérateur');
$athena_header_current = 'operateur';
$activeDashNav = 'operateur';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($htmlLang, ENT_QUOTES, 'UTF-8') ?>" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <?php $tailwindBaseUrl = $base; require base_path('views/partials/tailwind_cdn_or_build.php'); ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <?= $css('design-system.css') ?><?= $css('styles.css') ?><?= $css('app-update-modal.css') ?><?= $css('dashboard-impact.css') ?><?= $css('dashboard-refresh.css') ?><?= $css('dashboard-rail.css') ?><?= $css('announce-tiles.css') ?><?= $css('athena-header.css') ?><?= $css('navbar-info-banners.css') ?><?= $css('portal-footer.css') ?><?= $css('operateur-hub.css') ?>
</head>
<body class="dashboard-shell layout-light text-slate-900 selection:bg-emerald-500/25 selection:text-slate-900 antialiased" style="background:#f8fafc;">
<?php require base_path('views/partials/tenant_intervention_banner.php'); ?>
<?php require base_path('views/partials/plan_preview_banner.php'); ?>
<?php $baseUrl = $base; ?>
<?= $js('portal-alerts.js') ?><?= $js('ui_confirm_modal.js') ?><?= $js('portal_command_palette.js') ?><?= $js('dashboard-rail.js') ?><?= $js('athena-header.js') ?><?= $js('operateur-hub.js') ?>
<?php require base_path('views/partials/alert_banners.php'); ?>
    <div class="min-h-screen bg-[#f8fafc]">
    <main class="min-h-screen bg-[#f8fafc] text-slate-900">
        <div class="dash-cc dash-cc--rail">
            <?php require base_path('views/partials/dashboard_aside.php'); ?>
            <div class="dash-cc__main">
                <?php
                $athena_header_skip_banners = true;
                require base_path('views/partials/header_dashboard.php');
                require base_path('views/partials/navbar_info_banners.php');
                if (is_file($contentPath)) {
                    require $contentPath;
                }
                ?>
            </div>
        </div>
    </main>
    <?php require base_path('views/partials/portal_footer.php'); ?>
    </div>
<?php require base_path('views/partials/staff_marks_assets.php'); ?>
</body>
</html>
