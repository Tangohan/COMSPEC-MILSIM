<?php
$base = url('');
$htmlLang = function_exists('html_lang') ? html_lang() : 'fr';
$title = $title ?? (function_exists('i18n_phrase') ? i18n_phrase('nav', 'Tableau de bord — Athena') : 'Tableau de bord — Athena');
$showcase_training_feature = $showcase_training_feature ?? false;
$showcase_items = $showcase_items ?? [];
$showcase_kit_feature = $showcase_kit_feature ?? false;
$showcase_kit_items = $showcase_kit_items ?? [];
$can_manage_kit_pins = !empty($can_manage_kit_pins);
$dashboard_tenant_label = $dashboard_tenant_label ?? null;
$dashboard_is_default_tenant = !empty($dashboard_is_default_tenant);
$dashboard_tester_program = $dashboard_tester_program ?? null;
$doctrine_pending = is_array($doctrine_pending ?? null) ? $doctrine_pending : [];
$showcaseJsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
    $showcaseJsonFlags |= JSON_INVALID_UTF8_SUBSTITUTE;
}
$showcase_json = json_encode($showcase_items, $showcaseJsonFlags);
if (!is_string($showcase_json) || $showcase_json === '') {
    $showcase_json = '[]';
}
$showcase_kit_json = json_encode($showcase_kit_items, $showcaseJsonFlags);
if (!is_string($showcase_kit_json) || $showcase_kit_json === '') {
    $showcase_kit_json = '[]';
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($htmlLang, ENT_QUOTES, 'UTF-8') ?>" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
<?php
    $seo_og_title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $meta_description = $meta_description ?? (function_exists('i18n_phrase') ? i18n_phrase('nav', 'Tableau de bord Athena : accès rapide aux formations, messages et activités de votre communauté.') : 'Tableau de bord Athena : accès rapide aux formations, messages et activités de votre communauté.');
    require base_path('views/partials/seo_meta.php');
?>
    <?php $tailwindBaseUrl = $base; require base_path('views/partials/tailwind_cdn_or_build.php'); ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <?php if (is_file(base_path('public/assets/css/design-system.css'))): ?>
    <link href="<?= htmlspecialchars(asset_url('assets/css/design-system.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php endif; ?>
    <link href="<?= htmlspecialchars(asset_url('assets/css/styles.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php if (is_file(base_path('public/assets/css/app-update-modal.css'))): ?>
    <link href="<?= htmlspecialchars(asset_url('assets/css/app-update-modal.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php endif; ?>
    <?php if (is_file(base_path('public/assets/css/dashboard-impact.css'))): ?>
    <link href="<?= htmlspecialchars(asset_url('assets/css/dashboard-impact.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php endif; ?>
    <?php if (is_file(base_path('public/assets/css/dashboard-refresh.css'))): ?>
    <link href="<?= htmlspecialchars(asset_url('assets/css/dashboard-refresh.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php endif; ?>
    <?php if (is_file(base_path('public/assets/css/dashboard-catalog.css'))): ?>
    <link href="<?= htmlspecialchars(asset_url('assets/css/dashboard-catalog.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php endif; ?>
    <?php if (is_file(base_path('public/assets/css/dashboard-orbat.css'))): ?>
    <link href="<?= htmlspecialchars(asset_url('assets/css/dashboard-orbat.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php endif; ?>
    <?php if (is_file(base_path('public/assets/css/announce-tiles.css'))): ?>
    <link href="<?= htmlspecialchars(asset_url('assets/css/announce-tiles.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php endif; ?>
    <?php if (is_file(base_path('public/assets/css/athena-header.css'))): ?>
    <link href="<?= htmlspecialchars(asset_url('assets/css/athena-header.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php endif; ?>
    <?php if (is_file(base_path('public/assets/css/navbar-info-banners.css'))): ?>
    <link href="<?= htmlspecialchars(asset_url('assets/css/navbar-info-banners.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php endif; ?>
    <?php if (is_file(base_path('public/assets/css/portal-footer.css'))): ?>
    <link href="<?= htmlspecialchars(asset_url('assets/css/portal-footer.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php endif; ?>
    <?php
    $alpineSrc = '';
    if (is_file(base_path('public/assets/vendor/alpinejs/alpine.min.js'))) {
        $alpineSrc = asset_url('assets/vendor/alpinejs/alpine.min.js');
    } elseif (is_file(base_path('public/assets/js/alpine.min.js'))) {
        $alpineSrc = asset_url('assets/js/alpine.min.js');
    }
    $loadAlpineDashboard = $alpineSrc !== '' && (
        (!empty($showcase_training_feature) && !empty($showcase_items))
        || (!empty($showcase_kit_feature) && !empty($showcase_kit_items))
        || !$dashboard_is_default_tenant
        || !empty($candidate_enlistment_tracking ?? [])
        || !empty($my_applications_all ?? [])
    );
    ?>
    <?php
    $dashCatCourses = !empty($showcase_training_feature) && !empty($showcase_items);
    $dashCatKits = !empty($showcase_kit_feature) && !empty($showcase_kit_items);
    ?>
    <?php if ($dashCatCourses || $dashCatKits): ?>
    <script>
        <?php if ($dashCatCourses): ?>window.__dashboardShowcaseCourses = <?= $showcase_json ?>;<?php endif; ?>
        <?php if ($dashCatKits): ?>window.__dashboardShowcaseKits = <?= $showcase_kit_json ?>;<?php endif; ?>
    </script>
    <script src="<?= htmlspecialchars(asset_url('assets/js/dashboard-catalog.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php endif; ?>
    <?php if ($loadAlpineDashboard): ?>
    <script defer src="<?= htmlspecialchars($alpineSrc, ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php endif; ?>
</head>
<body class="dashboard-shell layout-light text-slate-900 selection:bg-emerald-500/25 selection:text-slate-900 antialiased" style="background:#f8fafc;">
<?php require base_path('views/partials/tenant_intervention_banner.php'); ?>
<?php $baseUrl = $base; ?>
<?php require base_path('views/partials/app_update_check.php'); ?>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/portal-alerts.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/ui_confirm_modal.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/portal_command_palette.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php if (is_file(base_path('public/assets/js/dashboard-rail.js'))): ?>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/dashboard-rail.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endif; ?>
<?php if (is_file(base_path('public/assets/js/dashboard-org-anomaly.js'))): ?>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/dashboard-org-anomaly.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endif; ?>
<?php if (is_file(base_path('public/assets/js/dashboard-site-support.js'))): ?>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/dashboard-site-support.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endif; ?>
<?php if (is_file(base_path('public/assets/js/dashboard-reveal.js'))): ?>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/dashboard-reveal.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endif; ?>
<?php if (is_file(base_path('public/assets/js/athena-header.js'))): ?>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/athena-header.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endif; ?>
<?php
// Sur le tableau de bord, les bandeaux classiques passent par les tuiles (pas de doublon).
// Les barres sous le menu (mini / Breaking / important) sont injectées via header_dashboard.
$alertBanners = [];
require base_path('views/partials/alert_banners.php');
?>
<?php require base_path('views/partials/forum_moderation_alerts.php'); ?>

    <div class="min-h-screen bg-[#f8fafc]">
    <main class="min-h-screen bg-[#f8fafc] text-slate-900">
        <?php require base_path('views/partials/layout_flash_toasts.php'); ?>
        <?php
        $communityMemberships = $communityMemberships ?? [];
        $candidate_enlistment_tracking = is_array($candidate_enlistment_tracking ?? null) ? $candidate_enlistment_tracking : [];
        $n = static function (string $fr): string {
            return htmlspecialchars(function_exists('i18n_phrase') ? i18n_phrase('nav', $fr) : $fr, ENT_QUOTES, 'UTF-8');
        };
        ?>

        <?php if ($dashboard_is_default_tenant): ?>
        <?php require base_path('views/partials/header_dashboard.php'); ?>
        <section class="dash-guest-hero dash-reveal relative overflow-hidden border-b border-[#0a1f18] bg-[#050505] text-white" id="dash-tour-join" data-dash-reveal>
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_20%_0%,rgba(4,120,87,0.35),transparent_55%)]" aria-hidden="true"></div>
            <div class="dash-guest-zone__inner relative">
                <?php if (!empty($account_is_solo)): ?>
                <div class="max-w-3xl">
                    <p class="mb-3 text-[11px] font-bold uppercase tracking-[0.22em] text-[#34d399]"><?= $n('Compte simple') ?></p>
                    <h2 class="mb-4 text-3xl font-black tracking-tight leading-[1.08] text-white md:text-5xl">
                        <?= $n('Votre espace personnel Athena') ?><span class="text-[#34d399]">.</span>
                    </h2>
                    <div class="mb-6 h-[3px] w-12 bg-[#047857]" aria-hidden="true"></div>
                    <p class="mb-8 max-w-2xl text-sm leading-relaxed text-white/75 md:text-base">
                        <?= $n('Vous utilisez Athena seul, sans organisation : votre profil et votre dossier, la liaison de votre jeu et le forum restent à vous. Rejoignez ou créez une communauté quand vous voudrez.') ?>
                    </p>
                    <div class="dash-start-hero-actions">
                        <a href="<?= htmlspecialchars(url('personnel/me'), ENT_QUOTES, 'UTF-8') ?>" class="dash-start-btn dash-start-btn--primary"><?= $n('Mon dossier') ?></a>
                        <a href="<?= htmlspecialchars(url('lier'), ENT_QUOTES, 'UTF-8') ?>" class="dash-start-btn dash-start-btn--ghost"><?= $n('Lier mon jeu') ?></a>
                        <a href="<?= htmlspecialchars(url('forum'), ENT_QUOTES, 'UTF-8') ?>" class="dash-start-link"><?= $n('Ouvrir le forum') ?></a>
                    </div>
                </div>
                <?php else: ?>
                <div class="max-w-3xl">
                    <p class="mb-3 text-[11px] font-bold uppercase tracking-[0.22em] text-[#34d399]"><?= $n('Sans organisation rattachée') ?></p>
                    <h2 class="mb-4 text-3xl font-black tracking-tight leading-[1.08] text-white md:text-5xl">
                        <?= $n('Rejoignez une unité ou créez votre communauté') ?><span class="text-[#34d399]">.</span>
                    </h2>
                    <div class="mb-6 h-[3px] w-12 bg-[#047857]" aria-hidden="true"></div>
                    <p class="mb-8 max-w-2xl text-sm leading-relaxed text-white/75 md:text-base">
                        <?= $n('Vous n’êtes rattaché à aucune organisation pour l’instant. Rejoignez votre unité avec son code communauté, postulez depuis le registre, ou créez l’espace de votre propre groupe.') ?>
                    </p>
                    <div class="dash-start-hero-actions">
                        <a href="<?= htmlspecialchars(url('communities/create'), ENT_QUOTES, 'UTF-8') ?>" class="dash-start-btn dash-start-btn--primary" data-dash-start="create">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                            <?= $n('Créer ma communauté') ?>
                        </a>
                        <a href="<?= htmlspecialchars(url('join'), ENT_QUOTES, 'UTF-8') ?>" class="dash-start-btn dash-start-btn--ghost" data-dash-start="join">
                            <?= $n('Rejoindre une communauté') ?>
                        </a>
                        <a href="<?= htmlspecialchars(url('communities'), ENT_QUOTES, 'UTF-8') ?>" class="dash-start-link">
                            <?= $n('Parcourir le registre des unités') ?>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($candidate_enlistment_tracking !== []): ?>
        <section class="dash-zone dash-guest-zone dash-reveal" data-dash-reveal aria-labelledby="dash-guest-tracking-title">
            <div class="dash-guest-zone__inner">
                <header class="dash-zone__head">
                    <p class="dash-zone__kicker"><?= $n('Candidatures existantes') ?></p>
                    <h3 id="dash-guest-tracking-title" class="dash-zone__title"><?= $n('Suivez l’état de vos dossiers') ?></h3>
                    <p class="dash-zone__lead"><?= $n('Retrouvez les candidatures déjà transmises et ouvrez le suivi du dossier.') ?></p>
                </header>
                <div class="grid gap-3 md:grid-cols-2">
                    <?php foreach ($candidate_enlistment_tracking as $track): ?>
                        <?php
                        $statusRaw = (string) ($track['status'] ?? 'submitted');
                        $statusLabel = [
                            'submitted' => function_exists('i18n_phrase') ? i18n_phrase('nav', 'En cours d’instruction') : 'En cours d’instruction',
                            'reviewed' => function_exists('i18n_phrase') ? i18n_phrase('nav', 'Accepté') : 'Accepté',
                            'rejected' => function_exists('i18n_phrase') ? i18n_phrase('nav', 'Refusé') : 'Refusé',
                            'blocked' => function_exists('i18n_phrase') ? i18n_phrase('nav', 'Non admis') : 'Non admis',
                        ][$statusRaw] ?? (function_exists('i18n_phrase') ? i18n_phrase('nav', 'En cours') : 'En cours');
                        $statusClass = match ($statusRaw) {
                            'reviewed' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
                            'rejected', 'blocked' => 'border-rose-200 bg-rose-50 text-rose-800',
                            default => 'border-sky-200 bg-sky-50 text-sky-800',
                        };
                        $tenantName = trim((string) ($track['tenant_name'] ?? (function_exists('i18n_phrase') ? i18n_phrase('nav', 'Communauté') : 'Communauté')));
                        $createdAt = (string) ($track['created_at'] ?? '');
                        $createdFmt = $createdAt !== '' ? date('d/m/Y H:i', strtotime($createdAt)) : '—';
                        $portalHref = is_string($track['candidate_portal_href'] ?? null) ? (string) $track['candidate_portal_href'] : null;
                        ?>
                        <article class="border border-[#e5e5e5] border-l-[3px] border-l-[#047857] bg-white p-5">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-sm font-bold text-[#161616]"><?= htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') ?></p>
                                <span class="inline-flex items-center border px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide <?= $statusClass ?>"><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                            <p class="mt-2 text-xs text-[#666]"><?= $n('Déposé le') ?> <?= htmlspecialchars($createdFmt, ENT_QUOTES, 'UTF-8') ?></p>
                            <div class="mt-4 flex flex-wrap gap-3">
                                <?php if ($portalHref !== null): ?>
                                <a href="<?= htmlspecialchars($portalHref, ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center bg-[#047857] px-4 py-2 text-[11px] font-bold uppercase tracking-wider text-white transition-colors hover:bg-[#065f46]">
                                    <?= $n('Ouvrir la page du dossier') ?>
                                </a>
                                <?php endif; ?>
                                <a href="<?= htmlspecialchars(url('communities'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center border border-[#e5e5e5] px-4 py-2 text-[11px] font-bold uppercase tracking-wider text-[#161616] transition-colors hover:bg-[#f6f6f6]">
                                    <?= $n('Voir les unités') ?>
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <?php
        $myApplicationsAllRoot = is_array($my_applications_all ?? null) ? $my_applications_all : [];
        ?>
        <?php if ($myApplicationsAllRoot !== []): ?>
        <section class="dash-zone dash-guest-zone dash-reveal" id="dash-tour-applications" data-dash-reveal aria-labelledby="dash-guest-apps-title">
            <div class="dash-guest-zone__inner" style="max-width:100rem;">
                <header class="dash-zone__head">
                    <p class="dash-zone__kicker"><?= $n('Vue d’ensemble') ?></p>
                    <h3 id="dash-guest-apps-title" class="dash-zone__title"><?= $n('Toutes mes candidatures') ?></h3>
                    <p class="dash-zone__lead"><?= $n('Le détail complet de vos dossiers déposés, quel que soit leur statut.') ?></p>
                </header>
                <?php require base_path('views/partials/dashboard_applications_table.php'); ?>
            </div>
        </section>
        <?php endif; ?>

        <section class="dash-zone dash-guest-zone dash-start dash-reveal" id="dash-start" data-dash-reveal aria-labelledby="dash-start-title">
            <div class="dash-guest-zone__inner">
                <header class="dash-zone__head">
                    <p class="dash-zone__kicker"><?= $n('Pour commencer') ?></p>
                    <?php if (!empty($account_is_solo)): ?>
                    <h3 id="dash-start-title" class="dash-zone__title"><?= $n('Quand vous voudrez : rejoindre ou créer une communauté') ?></h3>
                    <p class="dash-zone__lead"><?= $n('Votre compte simple reste tel quel tant que vous ne rejoignez aucune unité.') ?></p>
                    <?php else: ?>
                    <h3 id="dash-start-title" class="dash-zone__title"><?= $n('Trois façons d’utiliser Athena') ?></h3>
                    <p class="dash-zone__lead"><?= $n('Rejoignez la communauté de votre unité, créez la vôtre si vous dirigez un groupe, ou gardez un compte simple, sans organisation.') ?></p>
                    <?php endif; ?>
                </header>
                <div class="dash-start-grid<?= empty($account_is_solo) ? ' dash-start-grid--3' : '' ?>">
                    <article class="dash-start-card">
                        <span class="dash-start-card__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/></svg>
                        </span>
                        <p class="dash-start-card__kicker"><?= $n('Votre unité est déjà sur Athena') ?></p>
                        <h4 class="dash-start-card__title"><?= $n('Rejoindre une communauté') ?></h4>
                        <p class="dash-start-card__text"><?= $n('Entrez le code communauté fourni par votre unité, ou trouvez-la dans le registre, puis envoyez votre candidature.') ?></p>
                        <ul class="dash-start-card__list">
                            <li><?= $n('Le code ouvre directement la page de votre unité') ?></li>
                            <li><?= $n('Votre candidature est étudiée par son staff') ?></li>
                            <li><?= $n('Le suivi de vos dossiers apparaît sur ce tableau de bord') ?></li>
                        </ul>
                        <div class="dash-start-card__actions">
                            <a href="<?= htmlspecialchars(url('join'), ENT_QUOTES, 'UTF-8') ?>" class="dash-start-btn dash-start-btn--dark"><?= $n('Entrer un code') ?></a>
                            <a href="<?= htmlspecialchars(url('communities'), ENT_QUOTES, 'UTF-8') ?>" class="dash-start-btn dash-start-btn--outline"><?= $n('Voir le registre') ?></a>
                        </div>
                    </article>
                    <article class="dash-start-card dash-start-card--create">
                        <span class="dash-start-card__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M12 9v6M9 12h6"/></svg>
                        </span>
                        <p class="dash-start-card__kicker"><?= $n('Vous dirigez un groupe') ?></p>
                        <h4 class="dash-start-card__title"><?= $n('Créer ma communauté') ?></h4>
                        <p class="dash-start-card__text"><?= $n('Une communauté est l’espace de votre unité sur Athena : effectifs et ORBAT, formations, manœuvres, recrutement et carte ATAK partagés par tous vos membres.') ?></p>
                        <ul class="dash-start-card__list">
                            <li><?= $n('Gratuit pour démarrer') ?></li>
                            <li><?= $n('Formule Pro offerte 30 jours au fondateur') ?></li>
                            <li><?= $n('Assistant de création pas à pas, tout reste modifiable ensuite') ?></li>
                        </ul>
                        <div class="dash-start-card__actions">
                            <a href="<?= htmlspecialchars(url('communities/create'), ENT_QUOTES, 'UTF-8') ?>" class="dash-start-btn dash-start-btn--primary"><?= $n('Créer ma communauté') ?></a>
                            <a href="<?= htmlspecialchars(url('communities/create/guide'), ENT_QUOTES, 'UTF-8') ?>" class="dash-start-btn dash-start-btn--outline"><?= $n('Lire le guide de création') ?></a>
                        </div>
                    </article>
                    <?php if (empty($account_is_solo)): ?>
                    <article class="dash-start-card dash-start-card--solo">
                        <span class="dash-start-card__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/></svg>
                        </span>
                        <p class="dash-start-card__kicker"><?= $n('Pas d’unité pour l’instant') ?></p>
                        <h4 class="dash-start-card__title"><?= $n('Utiliser Athena seul') ?></h4>
                        <p class="dash-start-card__text"><?= $n('Un compte simple, sans organisation : votre profil et votre dossier, la liaison de votre jeu et le forum. Aucune communauté à gérer.') ?></p>
                        <ul class="dash-start-card__list">
                            <li><?= $n('Rien à configurer') ?></li>
                            <li><?= $n('Vous pourrez rejoindre ou créer une communauté plus tard') ?></li>
                            <li><?= $n('Vos données restent attachées à votre compte') ?></li>
                        </ul>
                        <div class="dash-start-card__actions">
                            <form method="post" action="<?= htmlspecialchars(url('account/usage-solo'), ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit" class="dash-start-btn dash-start-btn--dark"><?= $n('Garder un compte simple') ?></button>
                            </form>
                        </div>
                    </article>
                    <?php endif; ?>
                </div>
                <?php if (!empty($account_is_solo)): ?>
                <form method="post" action="<?= htmlspecialchars(url('account/usage-solo/annuler'), ENT_QUOTES, 'UTF-8') ?>" class="dash-start-solo-reset">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit"><?= $n('Revoir les trois façons d’utiliser Athena') ?></button>
                </form>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php if (!$dashboard_is_default_tenant): ?>
            <?php require base_path('views/partials/simple_account_offer.php'); ?>
            <?php require base_path('views/partials/dashboard_command_center.php'); ?>
        <?php endif; ?>
    </main>
    <?php require base_path('views/partials/portal_footer.php'); ?>
    </div>
    <?php
    $lmsModuleEntryAuto = null;
    require base_path('views/partials/lms_module_entry_modal.php');
    ?>
    <?php require base_path('views/partials/dashboard_ui_tour_boot.php'); ?>
</body>
</html>
