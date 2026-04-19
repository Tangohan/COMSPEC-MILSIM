<?php
$title = $title ?? 'Athena';
$content = $content ?? 'home';
$baseUrl = url('');
$communityShowcasePage = !empty($communityShowcasePage);
$communityRecruitmentOpeningPage = !empty($communityRecruitmentOpeningPage);
$isFormationWorkspace = function_exists('is_formation_workspace_request') && is_formation_workspace_request();
$isBackOfficeShell = !$isFormationWorkspace && function_exists('is_back_office_request') && is_back_office_request();
$isPlatformAdminShell = function_exists('is_platform_site_admin_shell_request') && is_platform_site_admin_shell_request();
$usesAdminSidebarShell = !empty($isBackOfficeShell) || !empty($isPlatformAdminShell);
$adminSidebarShellMobileTitle = !empty($isBackOfficeShell)
    ? 'Administration communauté'
    : (!empty($isPlatformAdminShell) ? 'Administration plateforme' : '');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> — Athena</title>
<?php
    $seo_og_title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . ' — Athena';
    require base_path('views/partials/seo_meta.php');
?>
<?php
    $tailwindBaseUrl = $baseUrl;
    require base_path('views/partials/tailwind_cdn_or_build.php');
?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&family=Source+Serif+4:ital,opsz,wght@0,8..60,400;0,8..60,600;1,8..60,400;1,8..60,600&display=swap" rel="stylesheet">
    <?php if ($communityShowcasePage || $communityRecruitmentOpeningPage): ?>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
      .community-showcase-grain::before {
        content: "";
        position: absolute;
        inset: 0;
        pointer-events: none;
        opacity: .04;
        background-image: radial-gradient(circle at 20% 20%, #000 0.5px, transparent 0.6px), radial-gradient(circle at 80% 70%, #000 0.5px, transparent 0.6px);
        background-size: 18px 18px;
      }
      .shadow-soft { box-shadow: 0 20px 70px -30px rgba(15,23,42,0.25); }
      .scrollbar-thin::-webkit-scrollbar { width: 8px; height: 8px; }
      .scrollbar-thin::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; }
      .scrollbar-thin::-webkit-scrollbar-track { background: transparent; }
      .community-public-vitrine .font-mono { font-family: 'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
    </style>
    <?php endif; ?>
    <?php if (is_file(base_path('public/assets/css/styles.css'))): ?>
    <link href="<?= $baseUrl ?>/assets/css/styles.css" rel="stylesheet">
    <?php endif; ?>
    <?php if (is_file(base_path('public/assets/css/portal-nav.css'))): ?>
    <link href="<?= $baseUrl ?>/assets/css/portal-nav.css" rel="stylesheet">
    <?php endif; ?>
    <?php if ((!empty($siteDocsPage) || !empty($siteDocsRefsPage)) && is_file(base_path('public/assets/css/site-docs.css'))): ?>
    <link href="<?= $baseUrl ?>/assets/css/site-docs.css" rel="stylesheet">
    <?php endif; ?>
    <?php
    $alpineLocal = base_path('public/assets/js/alpine.min.js');
    $alpineSrc = is_file($alpineLocal) ? $baseUrl . '/assets/js/alpine.min.js' : 'https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js';
?>
    <script defer src="<?= htmlspecialchars($alpineSrc) ?>"></script>
    <style>
      select.bo-select {
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b' stroke-width='2'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 0.65rem center;
        background-size: 1.125rem 1.125rem;
      }
      select.bo-select::-ms-expand { display: none; }
    </style>
    <?php if (!empty($usesAdminSidebarShell)): ?>
    <style>[x-cloak]{display:none!important}</style>
    <?php endif; ?>
</head>
<body class="layout-light bg-slate-50 text-slate-900 font-sans antialiased min-h-screen">
    <div class="grain" aria-hidden="true"></div>
    <?php if (!empty($isFormationWorkspace)): ?>
        <?php require base_path('views/partials/formation_workspace_chrome.php'); ?>
    <?php else: ?>
        <?php require base_path('views/partials/header_portal.php'); ?>
    <?php endif; ?>
    <script defer src="<?= htmlspecialchars($baseUrl) ?>/assets/js/portal-alerts.js"></script>
    <script defer src="<?= htmlspecialchars($baseUrl) ?>/assets/js/navigation.js"></script>
    <script defer src="<?= htmlspecialchars($baseUrl) ?>/assets/js/ui_confirm_modal.js"></script>
    <script defer src="<?= htmlspecialchars($baseUrl) ?>/assets/js/portal_command_palette.js"></script>
    <?php require base_path('views/partials/alert_banners.php'); ?>
    <?php require base_path('views/partials/forum_moderation_alerts.php'); ?>
    <main class="<?= !empty($usesAdminSidebarShell) ? 'min-h-[calc(100dvh-5rem)] lg:min-h-[calc(100dvh-5.5rem)]' : 'min-h-[80vh]' ?>">
        <?php require base_path('views/partials/layout_flash_toasts.php'); ?>
        <?php if (!empty($usesAdminSidebarShell)): ?>
        <div
            x-data="{ navOpen: false }"
            @keydown.escape.window="navOpen = false"
            class="relative z-[1] isolate flex min-h-[inherit] flex-col bg-slate-50 lg:flex-row"
        >
            <div class="sticky top-0 z-[90] flex items-center gap-3 border-b border-slate-200 bg-white px-3 py-2.5 shadow-sm lg:hidden">
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-100"
                    @click="navOpen = true"
                    aria-expanded="false"
                    :aria-expanded="navOpen ? 'true' : 'false'"
                >
                    <svg class="h-5 w-5 text-slate-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
                    Menu
                </button>
                <span class="truncate text-sm font-bold text-slate-900"><?= htmlspecialchars($adminSidebarShellMobileTitle, ENT_QUOTES, 'UTF-8') ?></span>
            </div>

            <div
                x-show="navOpen"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 z-[200] bg-slate-900/60 backdrop-blur-[2px] lg:hidden"
                x-cloak
                @click="navOpen = false"
                aria-hidden="true"
            ></div>

            <aside
                class="fixed inset-y-0 left-0 z-[210] w-[min(100%,288px)] max-w-full border-r border-slate-800 bg-slate-950 shadow-2xl transition-transform duration-200 ease-out lg:static lg:z-auto lg:w-64 lg:shrink-0 lg:!translate-x-0 lg:self-stretch lg:border-r lg:shadow-none xl:w-72"
                :class="navOpen ? 'translate-x-0' : '-translate-x-full'"
                id="<?= !empty($isBackOfficeShell) ? 'back-office-sidebar' : 'platform-admin-sidebar' ?>"
                aria-label="Menu latéral"
                @click.capture="if ($event.target.closest('a')) navOpen = false"
            >
                <div class="flex h-full max-h-screen min-h-0 flex-col lg:max-h-none lg:min-h-[inherit]">
                    <div class="flex items-center justify-end border-b border-slate-800/80 px-3 py-2 lg:hidden">
                        <button
                            type="button"
                            class="rounded-lg px-3 py-1.5 text-sm font-semibold text-slate-300 hover:bg-slate-800 hover:text-white"
                            @click="navOpen = false"
                        >
                            Fermer
                        </button>
                    </div>
                    <?php if (!empty($isBackOfficeShell)): ?>
                        <?php require base_path('views/partials/back_office_sidebar.php'); ?>
                    <?php else: ?>
                        <?php require base_path('views/partials/platform_admin_sidebar.php'); ?>
                    <?php endif; ?>
                </div>
            </aside>

            <div class="relative z-[1] flex min-h-0 min-w-0 flex-1 flex-col bg-slate-50">
                <?php
                $contentPath = str_replace('.', '/', $content);
                $innerPath = base_path('views/' . $contentPath . '.php');
                if (is_file($innerPath)) {
                    require $innerPath;
                } else {
                    echo '<div class="max-w-5xl mx-auto px-6 py-12"><p>Vue non trouvée.</p></div>';
                }
                ?>
            </div>
        </div>
        <?php else: ?>
        <?php
        $contentPath = str_replace('.', '/', $content);
        $innerPath = base_path('views/' . $contentPath . '.php');
        if (is_file($innerPath)) {
            require $innerPath;
        } else {
            echo '<div class="max-w-5xl mx-auto px-6 py-12"><p>Vue non trouvée.</p></div>';
        }
        ?>
        <?php endif; ?>
    </main>
    <?php if (empty($trainingAdminNav) && ($showPortalFooter ?? true) && empty($usesAdminSidebarShell) && empty($isFormationWorkspace)): ?>
    <footer class="mt-14 border-t border-slate-200 bg-gradient-to-b from-white to-slate-50/80">
        <div class="mx-auto grid max-w-6xl gap-10 px-6 py-12 md:grid-cols-12 md:py-14">
            <div class="md:col-span-5">
                <p class="text-[10px] font-black uppercase tracking-[0.32em] text-emerald-700">Athena Compsec</p>
                <h2 class="mt-3 text-2xl font-black tracking-tight text-slate-900">Le portail pro pour unités MILSIM Arma 3.</h2>
                <p class="mt-4 max-w-md text-sm leading-relaxed text-slate-600">
                    Centralisez le recrutement, la présence, les formations et la coordination opérationnelle dans une interface claire et fiable.
                </p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <a href="<?= url('register') ?>" class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2.5 text-[10px] font-black uppercase tracking-[0.14em] text-white transition hover:bg-slate-800">
                        Créer un compte
                    </a>
                    <a href="<?= url('communities') ?>" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-[10px] font-black uppercase tracking-[0.14em] text-slate-800 transition hover:border-slate-400 hover:bg-slate-100">
                        Explorer les communautés
                    </a>
                </div>
            </div>

            <div class="grid gap-8 sm:grid-cols-3 md:col-span-7">
                <div>
                    <h3 class="text-[10px] font-black uppercase tracking-[0.24em] text-slate-500">Accès rapide</h3>
                    <ul class="mt-3 space-y-2 text-sm">
                        <li><a href="<?= url('home') ?>" class="text-slate-700 transition hover:text-emerald-700">Accueil</a></li>
                        <li><a href="<?= url('documents') ?>" class="text-slate-700 transition hover:text-emerald-700">Documents</a></li>
                        <li><a href="<?= url('formations') ?>" class="text-slate-700 transition hover:text-emerald-700">Formations</a></li>
                        <li><a href="<?= url('atak') ?>" class="text-slate-700 transition hover:text-emerald-700">ATAK &amp; Cartographie</a></li>
                    </ul>
                </div>

                <div>
                    <h3 class="text-[10px] font-black uppercase tracking-[0.24em] text-slate-500">Plateforme</h3>
                    <ul class="mt-3 space-y-2 text-sm">
                        <li><a href="<?= url('enlistment') ?>" class="text-slate-700 transition hover:text-emerald-700">Enrôlement</a></li>
                        <li><a href="<?= url('overwatch') ?>" class="text-slate-700 transition hover:text-emerald-700">Overwatch</a></li>
                        <li><a href="<?= url('tacmap') ?>" class="text-slate-700 transition hover:text-emerald-700">Tacmap</a></li>
                        <li><a href="<?= url('equipment') ?>" class="text-slate-700 transition hover:text-emerald-700">Fiches matériel</a></li>
                    </ul>
                </div>

                <div>
                    <h3 class="text-[10px] font-black uppercase tracking-[0.24em] text-slate-500">Légal</h3>
                    <div class="mt-3 flex flex-col gap-2 text-sm">
                        <?php
                        $legal_link_class = 'text-slate-700 transition hover:text-emerald-700 font-medium';
                        require base_path('views/partials/legal_site_links.php');
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="border-t border-slate-200/80 bg-white/80">
            <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-3 px-6 py-4 text-center sm:flex-row sm:text-left">
                <p class="text-xs text-slate-500">© <?= date('Y') ?> Athena Compsec. Tous droits réservés.</p>
                <p class="text-[11px] font-semibold text-slate-500">SaaS RH tactique pour communautés MILSIM.</p>
            </div>
        </div>
    </footer>
    <?php endif; ?>
    <?php require base_path('views/partials/community_report_modal.php'); ?>
    <?php require base_path('views/partials/portal_help_modal.php'); ?>
    <?php require base_path('views/partials/analytics_beacon.php'); ?>
    <?php require base_path('views/partials/cookie_banner.php'); ?>
</body>
</html>
