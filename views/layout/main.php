<?php
$title = $title ?? 'Athena';
$content = $content ?? 'home';
$baseUrl = url('');
$communityShowcasePage = !empty($communityShowcasePage);
$communityRecruitmentOpeningPage = !empty($communityRecruitmentOpeningPage);
$isBackOfficeShell = function_exists('is_back_office_request') && is_back_office_request();
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
    <?php if (!empty($usesAdminSidebarShell)): ?>
    <style>[x-cloak]{display:none!important}</style>
    <?php endif; ?>
</head>
<body class="layout-light bg-slate-50 text-slate-900 font-sans antialiased min-h-screen">
    <div class="grain" aria-hidden="true"></div>
    <?php require base_path('views/partials/header_portal.php'); ?>
    <script defer src="<?= htmlspecialchars($baseUrl) ?>/assets/js/portal-alerts.js"></script>
    <script defer src="<?= htmlspecialchars($baseUrl) ?>/assets/js/navigation.js"></script>
    <?php require base_path('views/partials/alert_banners.php'); ?>
    <?php require base_path('views/partials/forum_moderation_alerts.php'); ?>
    <main class="<?= !empty($usesAdminSidebarShell) ? 'min-h-[calc(100dvh-5rem)] lg:min-h-[calc(100dvh-5.5rem)]' : 'min-h-[80vh]' ?>">
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
    <?php if (empty($trainingAdminNav) && ($showPortalFooter ?? true) && empty($usesAdminSidebarShell)): ?>
    <footer class="border-t border-slate-200 py-6 mt-12">
        <div class="max-w-5xl mx-auto px-6 flex flex-col sm:flex-row items-center justify-center gap-3 sm:gap-6 text-center text-xs text-slate-500">
            <span>Athena — SaaS RH tactique MILSIM Arma 3</span>
            <span class="hidden sm:inline text-slate-300" aria-hidden="true">|</span>
            <span class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 max-w-full">
                <?php
                $legal_link_class = 'text-slate-600 hover:text-emerald-700 font-medium';
                require base_path('views/partials/legal_site_links.php');
                ?>
            </span>
        </div>
    </footer>
    <?php endif; ?>
    <?php require base_path('views/partials/community_report_modal.php'); ?>
    <?php require base_path('views/partials/portal_help_modal.php'); ?>
    <?php require base_path('views/partials/analytics_beacon.php'); ?>
    <?php require base_path('views/partials/cookie_banner.php'); ?>
</body>
</html>
