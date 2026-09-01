<?php
$title = $title ?? 'Athena';
$content = $content ?? 'home';
$baseUrl = url('');
$communityShowcasePage = !empty($communityShowcasePage);
$communityRegistryPage = !empty($communityRegistryPage);
$communityRecruitmentOpeningPage = !empty($communityRecruitmentOpeningPage);
$communityReelsPage = !empty($communityReelsPage);
$hideAdminSidebar = !empty($hideAdminSidebar);
$isBackOfficeShell = !empty($isBackOfficeShell)
    || (function_exists('is_back_office_request') && is_back_office_request());
$isPlatformAdminShell = !empty($isPlatformAdminShell)
    || (function_exists('is_platform_site_admin_shell_request') && is_platform_site_admin_shell_request());
$isFormationWorkspace = !empty($isFormationWorkspace)
    || (function_exists('is_formation_workspace_request') && is_formation_workspace_request());

foreach (\App\Support\BackOfficePageContext::apply(get_defined_vars()) as $_boKey => $_boVal) {
    ${$_boKey} = $_boVal;
}

/* Re-assert après BackOfficePageContext (évite de perdre le flag vitrine). */
$communityShowcasePage = !empty($communityShowcasePage) || (($publicLayout ?? '') === 'showcase');
$communityRegistryPage = !empty($communityRegistryPage);
$communityRecruitmentOpeningPage = !empty($communityRecruitmentOpeningPage);
$communityReelsPage = !empty($communityReelsPage);

$usesAdminSidebarShell = (!$hideAdminSidebar) && (!empty($isBackOfficeShell) || !empty($isPlatformAdminShell) || !empty($isFormationWorkspace));
$adminSidebarShellMobileTitle = !empty($isBackOfficeShell)
    ? 'Administration communauté'
    : (!empty($isPlatformAdminShell)
        ? 'Administration plateforme'
        : (!empty($isFormationWorkspace) ? 'Pilotage des formations' : ''));
/**
 * Aside BO dépliable (rail étroit ~4.5rem → expand au survol / focus).
 * Activé sur tout le shell back-office / espace formation (réf. mes-formations / effectifs).
 * Opt-out possible : $backOfficeHoverRail = false depuis un contrôleur.
 * La largeur au repos reste stable ; le panneau s’ouvre en overlay sans écraser le contenu (min-w-0).
 */
$backOfficeHoverRail = (!empty($isBackOfficeShell) || !empty($isFormationWorkspace))
    && (($backOfficeHoverRail ?? true) !== false)
    && empty($isBackOfficeShell);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(function_exists('html_lang') ? html_lang() : 'fr', ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> — Athena</title>
<?php
    $seo_og_title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . ' — Athena';
    require base_path('views/partials/seo_meta.php');
?>
    <link rel="manifest" href="<?= htmlspecialchars($baseUrl) ?>/manifest.webmanifest">
    <meta name="theme-color" content="#0f172a">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($baseUrl) ?>/assets/icons/athena-192.png">
<?php
    $tenantFaviconUrl = null;
    try {
        $tidHead = (int) (\App\Core\Session::get('tenant_id') ?? 0);
        if ($tidHead > 1) {
            $brandingHead = \App\Core\Container::get(\App\Repositories\TenantBrandingRepository::class)->findByTenantId($tidHead);
            $favRaw = trim((string) ($brandingHead['favicon_url'] ?? ''));
            if ($favRaw !== '') {
                $tenantFaviconUrl = $favRaw;
            }
        }
    } catch (\Throwable) {
        $tenantFaviconUrl = null;
    }
?>
<?php if ($tenantFaviconUrl !== null): ?>
    <link rel="icon" href="<?= htmlspecialchars($tenantFaviconUrl, ENT_QUOTES, 'UTF-8') ?>">
<?php endif; ?>
<?php
    $tailwindBaseUrl = $baseUrl;
    require base_path('views/partials/tailwind_cdn_or_build.php');
?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <?php if (is_file(base_path('public/assets/css/design-system.css'))): ?>
    <link href="<?= htmlspecialchars(asset_url('assets/css/design-system.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php endif; ?>
    <?php if (is_file(base_path('public/assets/css/img-fallback.css'))): ?>
    <link href="<?= htmlspecialchars(asset_url('assets/css/img-fallback.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php endif; ?>
<?php
    $cdnPhase = 'head';
    $cdnPreset = 'portal';
    // $cdnLibs : null = défauts (icons + animation) ; false/'none' = désactivé ; array = packs explicites
    require base_path('views/partials/cdn_media_libs.php');
?>
    <?php if ($communityShowcasePage || $communityRecruitmentOpeningPage || $communityRegistryPage || $communityReelsPage): ?>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <?php
      /* Vitrine : LiteSpeed a mis en cache un 404 sur community-landing.css (et alias).
         On injecte le CSS lu par PHP (inline) plutôt que de pointer vers l’asset HTTP cassé. */
      $communityVitrineCssLinked = false;
      if (($communityShowcasePage || $communityRecruitmentOpeningPage) && !$communityReelsPage) {
          $communityVitrineCssLinked = (bool) require base_path('views/partials/community_vitrine_css.php');
          if (!$communityVitrineCssLinked) {
              foreach (['athena-community-vitrine.css', 'cl-vitrine-20260728a.css', 'cl-vitrine.css', 'community-landing.css'] as $communityCssCandidate) {
                  $communityCssFs = base_path('public/assets/css/' . $communityCssCandidate);
                  if (!is_readable($communityCssFs)) {
                      continue;
                  }
                  $communityCssHref = asset_url('assets/css/' . $communityCssCandidate) . '&t=' . (int) filemtime($communityCssFs);
                  echo '    <link href="' . htmlspecialchars($communityCssHref, ENT_QUOTES, 'UTF-8') . '" rel="stylesheet">' . "\n";
                  $communityVitrineCssLinked = true;
                  break;
              }
              unset($communityCssCandidate, $communityCssFs, $communityCssHref);
          }
      }
      $communityCssLinks = [
          [$communityReelsPage, 'community-reels.css'],
          [$communityRegistryPage, 'community-registry.css'],
      ];
      foreach ($communityCssLinks as [$communityCssOn, $communityCssFile]) {
          if (!$communityCssOn) {
              continue;
          }
          $communityCssFs = base_path('public/assets/css/' . $communityCssFile);
          if (!is_readable($communityCssFs)) {
              continue;
          }
          $communityCssHref = asset_url('assets/css/' . $communityCssFile) . '&t=' . (int) filemtime($communityCssFs);
          ?>
    <link href="<?= htmlspecialchars($communityCssHref, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php
      }
      unset($communityVitrineCssLinked, $communityCssLinks, $communityCssOn, $communityCssFile, $communityCssFs, $communityCssHref);
    ?>
    <style>
      .community-public-vitrine,
      .community-landing,
      .community-registry,
      .community-reels {
        font-family: 'Archivo', system-ui, sans-serif;
      }
      .community-public-vitrine .font-mono,
      .community-landing .font-mono,
      .cl-mono,
      .cr-mono {
        font-family: 'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
      }
    </style>
    <?php endif; ?>
    <?php if (is_file(base_path('public/assets/css/styles.css'))): ?>
    <link href="<?= htmlspecialchars(asset_url('assets/css/styles.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php endif; ?>
    <?php if (is_file(base_path('public/assets/css/app-update-modal.css'))): ?>
    <link href="<?= htmlspecialchars(asset_url('assets/css/app-update-modal.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php endif; ?>
    <?php if (is_file(base_path('public/assets/css/portal-nav.css'))): ?>
    <link href="<?= htmlspecialchars(asset_url('assets/css/portal-nav.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php endif; ?>
    <?php if (is_file(base_path('public/assets/css/portal-footer.css'))): ?>
    <link href="<?= htmlspecialchars(asset_url('assets/css/portal-footer.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php endif; ?>
    <?php if (is_file(base_path('public/assets/css/navbar-info-banners.css'))): ?>
    <link href="<?= htmlspecialchars(asset_url('assets/css/navbar-info-banners.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php endif; ?>
    <?php if (is_file(base_path('public/assets/css/athena-header.css'))): ?>
    <link href="<?= htmlspecialchars(asset_url('assets/css/athena-header.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php endif; ?>
    <?php if (!empty($siteDocsPage) || !empty($siteDocsRefsPage) || !empty($overwatchModDocsPage)): ?>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <?php if (is_file(base_path('public/assets/css/site-docs.css'))): ?>
    <link href="<?= htmlspecialchars(asset_url('assets/css/site-docs.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php endif; ?>
    <?php if (!empty($overwatchModDocsPage) && is_file(base_path('public/assets/css/overwatch-mod-docs.css'))): ?>
    <link href="<?= htmlspecialchars(asset_url('assets/css/overwatch-mod-docs.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php endif; ?>
    <?php endif; ?>
    <?php if (!empty($accountHubPage) && is_file(base_path('public/assets/css/account-hub.css'))): ?>
    <link href="<?= htmlspecialchars(asset_url('assets/css/account-hub.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php endif; ?>
    <?php if (!empty($opsWorkspacePage) && is_file(base_path('public/assets/css/ops-workspace.css'))): ?>
    <link href="<?= htmlspecialchars(asset_url('assets/css/ops-workspace.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php endif; ?>
    <?php if (!empty($equipmentHubPage) && is_file(base_path('public/assets/css/equipment-hub.css'))): ?>
    <link href="<?= htmlspecialchars(asset_url('assets/css/equipment-hub.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php endif; ?>
    <?php
    $alpineLocal = base_path('public/assets/js/alpine.min.js');
    $alpineSrc = is_file($alpineLocal) ? asset_url('assets/js/alpine.min.js') : 'https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js';
?>
    <script defer src="<?= htmlspecialchars($alpineSrc) ?>"></script>
    <?php if (is_file(base_path('public/assets/js/img-fallback.js'))): ?>
    <script src="<?= htmlspecialchars(asset_url('assets/js/img-fallback.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php endif; ?>
    <script>
      window.APP_VERSION = <?= json_encode(platform_app_version(), JSON_UNESCAPED_UNICODE) ?>;
      window.APP_BASE_URL = <?= json_encode(rtrim((string) url(''), '/'), JSON_UNESCAPED_UNICODE) ?>;
    </script>
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
    <style>
      [x-cloak]{display:none!important}
      body.bo-shell {
        font-family: Archivo, Inter, system-ui, -apple-system, Segoe UI, sans-serif;
      }
      /* Shell admin : largeur aside bornée pour ne pas écraser la colonne contenu */
      #back-office-sidebar,
      #platform-admin-sidebar {
        box-sizing: border-box;
        flex-shrink: 0;
        width: min(100%, 20rem);
        max-width: min(100%, 20rem);
        overflow-x: hidden;
      }
      @media (min-width: 1024px) {
        #back-office-sidebar:not(.ath-sidebar-aside),
        #platform-admin-sidebar {
          position: static;
          width: 18rem;
          min-width: 18rem;
          max-width: 18rem;
          transform: none !important;
        }
        #back-office-sidebar.ath-sidebar-aside:not(.is-collapsed) {
          width: var(--ath-side-w, 248px);
          min-width: var(--ath-side-w, 248px);
          max-width: var(--ath-side-w, 248px);
        }
      }
      @media (min-width: 1280px) {
        #back-office-sidebar:not(.ath-sidebar-aside),
        #platform-admin-sidebar {
          width: 20rem;
          min-width: 20rem;
          max-width: 20rem;
        }
      }
      /* Hover-rail : réserve + largeurs (détail dans back-office-rail.css) */
      <?php if (!empty($backOfficeHoverRail)): ?>
      @media (min-width: 1024px) {
        body.bo-shell--hover-rail .bo-shell-row--hover-rail {
          padding-left: 4.5rem;
        }
        body.bo-shell--hover-rail #back-office-sidebar.bo-aside--hover-rail {
          width: 4.5rem;
          min-width: 4.5rem;
          max-width: 4.5rem;
          overflow: visible;
        }
      }
      <?php endif; ?>
    </style>
    <?php endif; ?>
    <?php if ((!empty($isBackOfficeShell) || !empty($isFormationWorkspace)) && is_file(base_path('public/assets/css/back-office-rail.css'))): ?>
    <link href="<?= htmlspecialchars(asset_url('assets/css/back-office-rail.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php endif; ?>
    <?php if (!empty($isBackOfficeShell) && is_file(base_path('public/assets/css/back-office-shell.css'))): ?>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900&display=swap" rel="stylesheet">
    <link href="<?= htmlspecialchars(asset_url('assets/css/back-office-shell.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php elseif (!empty($isPlatformAdminShell)): ?>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900&display=swap" rel="stylesheet">
    <?php endif; ?>
    <?php
    $backOfficePageCss = isset($backOfficePageCss) && is_array($backOfficePageCss) ? $backOfficePageCss : [];
    foreach ($backOfficePageCss as $boCssRel):
        $boCssRel = ltrim(str_replace('\\', '/', (string) $boCssRel), '/');
        if ($boCssRel === '' || str_contains($boCssRel, '..')) {
            continue;
        }
        $boCssPath = base_path('public/assets/css/' . $boCssRel);
        if (!is_file($boCssPath)) {
            continue;
        }
        ?>
    <link href="<?= htmlspecialchars(asset_url('assets/css/' . $boCssRel), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php endforeach; ?>
</head>
<?php
$showBottomNav = (bool) \App\Core\Session::get('user_id')
    && empty($usesAdminSidebarShell)
    && empty($communityShowcasePage)
    && empty($communityRegistryPage)
    && empty($communityReelsPage)
    && empty($hide_bottom_nav);
$bodyClasses = 'layout-light bg-slate-50 text-slate-900 font-sans antialiased min-h-screen';
if ($communityReelsPage) {
    $bodyClasses = 'community-reels-page bg-[#070a0c] text-white font-sans antialiased min-h-screen';
}
if ($communityShowcasePage) {
    $bodyClasses .= ' community-showcase-page';
}
if (!empty($layoutMainCompact)) {
    $bodyClasses .= ' layout-page-compact';
}
if ($showBottomNav) {
    $bodyClasses .= ' athena-has-bottom-nav';
}
if (!empty($backOfficeHoverRail)) {
    $bodyClasses .= ' bo-shell--hover-rail';
}
if (!empty($usesAdminSidebarShell)) {
    $bodyClasses .= ' bo-shell';
}
if (!empty($isBackOfficeShell)) {
    $bodyClasses .= ' ath-bo-shell';
}
?>
<body class="<?= htmlspecialchars($bodyClasses, ENT_QUOTES, 'UTF-8') ?>">
    <?php if (empty($communityReelsPage)): ?>
    <div class="grain" aria-hidden="true"></div>
    <?php endif; ?>
    <?php if (empty($isBackOfficeShell) && empty($communityReelsPage) && empty($communityShowcasePage)): ?>
    <?php require base_path('views/partials/header_portal.php'); ?>
    <?php endif; ?>
    <script defer src="<?= htmlspecialchars(asset_url('assets/js/portal-alerts.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script defer src="<?= htmlspecialchars(asset_url('assets/js/navigation.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script defer src="<?= htmlspecialchars(asset_url('assets/js/ui_confirm_modal.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script defer src="<?= htmlspecialchars(asset_url('assets/js/portal_command_palette.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script defer src="<?= htmlspecialchars(asset_url('assets/js/app-version-check.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php if (is_file(base_path('public/assets/js/athena-header.js'))): ?>
    <script defer src="<?= htmlspecialchars(asset_url('assets/js/athena-header.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php endif; ?>
    <?php if (!empty($isBackOfficeShell) && is_file(base_path('public/assets/js/back-office-sidebar.js'))): ?>
    <script defer src="<?= htmlspecialchars(asset_url('assets/js/back-office-sidebar.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php endif; ?>
    <?php if (!empty($isBackOfficeShell) && is_file(base_path('public/assets/js/back-office-search.js'))): ?>
    <script defer src="<?= htmlspecialchars(asset_url('assets/js/back-office-search.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php elseif ((!empty($isBackOfficeShell) || !empty($isFormationWorkspace)) && is_file(base_path('public/assets/js/dashboard-rail.js'))): ?>
    <script defer src="<?= htmlspecialchars(asset_url('assets/js/dashboard-rail.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php endif; ?>
    <?php if (empty($usesAdminSidebarShell) && empty($communityReelsPage) && empty($communityShowcasePage)): ?>
    <?php require base_path('views/partials/navbar_info_banners.php'); ?>
    <?php require base_path('views/partials/alert_banners.php'); ?>
    <?php require base_path('views/partials/forum_moderation_alerts.php'); ?>
    <?php endif; ?>
    <?php if (empty($usesAdminSidebarShell)): ?>
    <?php require base_path('views/partials/advanced_fiche_edit_banner.php'); ?>
    <?php endif; ?>
    <main class="<?= (!empty($communityReelsPage) || !empty($communityShowcasePage)) ? 'min-h-dvh' : (!empty($usesAdminSidebarShell) ? (!empty($isBackOfficeShell) ? 'min-h-dvh' : 'min-h-[calc(100dvh-5rem)] lg:min-h-[calc(100dvh-5.5rem)]') : (!empty($layoutMainCompact) ? 'min-h-0' : 'min-h-[80vh]')) ?>">
        <?php if (empty($communityReelsPage) && empty($isBackOfficeShell)): ?>
        <?php require base_path('views/partials/layout_flash_toasts.php'); ?>
        <?php endif; ?>
        <?php if (!empty($usesAdminSidebarShell)): ?>
        <div
            x-data="{ navOpen: false }"
            @keydown.escape.window="navOpen = false"
            class="bo-shell-row ath-shell-row relative z-[1] isolate flex min-h-[inherit] flex-col lg:flex-row<?= !empty($backOfficeHoverRail) ? ' bo-shell-row--hover-rail' : '' ?>"
        >
            <?php if (!empty($isBackOfficeShell)): ?>
            <div class="ath-mobile-bar lg:hidden">
                <button
                    type="button"
                    class="ath-mobile-bar__menu"
                    @click="navOpen = true"
                    aria-expanded="false"
                    :aria-expanded="navOpen ? 'true' : 'false'"
                >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16" stroke-linecap="round"/></svg>
                    Menu
                </button>
                <span class="ath-mobile-bar__title"><?= htmlspecialchars($adminSidebarShellMobileTitle, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <?php else: ?>
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
            <?php endif; ?>

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
                class="fixed inset-y-0 left-0 z-[210] w-80 max-w-full overflow-x-hidden border-r border-white/10 bg-black text-white shadow-2xl transition-transform duration-200 ease-out lg:static lg:z-auto lg:shrink-0 lg:!translate-x-0 lg:self-stretch lg:border-r lg:shadow-none<?= (!empty($isBackOfficeShell) || !empty($isFormationWorkspace)) ? ' back-office-rail-aside' : '' ?><?= !empty($isBackOfficeShell) ? ' ath-sidebar-aside' : '' ?><?= !empty($backOfficeHoverRail) ? ' bo-aside--hover-rail' : '' ?>"
                :class="navOpen ? 'translate-x-0' : '-translate-x-full'"
                id="<?= (!empty($isBackOfficeShell) || !empty($isFormationWorkspace)) ? 'back-office-sidebar' : 'platform-admin-sidebar' ?>"
                aria-label="Menu latéral"
                @click.capture="if ($event.target.closest('a')) navOpen = false"
            >
                <div class="flex h-full max-h-screen min-h-0 flex-col lg:max-h-none lg:min-h-[inherit]">
                    <div class="flex items-center justify-end border-b border-white/10 px-3 py-2 lg:hidden">
                        <button
                            type="button"
                            class="rounded-lg px-3 py-1.5 text-sm font-semibold text-slate-300 hover:bg-white/5 hover:text-white"
                            @click="navOpen = false"
                        >
                            Fermer
                        </button>
                    </div>
                    <?php if (!empty($isBackOfficeShell) || !empty($isFormationWorkspace)): ?>
                        <?php require base_path('views/partials/back_office_sidebar.php'); ?>
                    <?php else: ?>
                        <?php require base_path('views/partials/platform_admin_sidebar.php'); ?>
                    <?php endif; ?>
                </div>
            </aside>

            <div class="ath-main relative z-[1] flex min-h-0 min-w-0 flex-1 flex-col overflow-x-hidden<?= (!empty($isBackOfficeShell)) ? '' : (( !empty($isFormationWorkspace) || !empty($isPlatformAdminShell)) ? ' bg-[#050505]' : ' bg-slate-50') ?>">
                <?php
                // Bandeaux TRANSMISSION / annonces navbar : portail membre uniquement (pas le shell back-office).
                if (empty($isBackOfficeShell)) {
                    require base_path('views/partials/navbar_info_banners.php');
                    require base_path('views/partials/alert_banners.php');
                }
                require base_path('views/partials/forum_moderation_alerts.php');
                require base_path('views/partials/advanced_fiche_edit_banner.php');
                ?>
                <?php if (!empty($isBackOfficeShell)): ?>
                    <?php require base_path('views/partials/back_office_topbar.php'); ?>
                <?php endif; ?>
                <div class="ath-main__body flex min-h-0 min-w-0 flex-1 flex-col">
                <?php if (!empty($isBackOfficeShell) && empty($boSkipPageHead)): ?>
                    <?php require base_path('views/partials/back_office_page_head.php'); ?>
                <?php endif; ?>
                <?php if (!empty($isBackOfficeShell) && empty($boSkipSessionFlashes)): ?>
                <div class="ath-main__flashes ath-rise">
                    <?php require base_path('views/partials/bo_session_flashes.php'); ?>
                </div>
                <?php endif; ?>
                <?php
                $contentPath = str_replace('.', '/', $content);
                $innerPath = base_path('views/' . $contentPath . '.php');
                if (is_file($innerPath)) {
                    require $innerPath;
                } else {
                    echo '<div class="w-full px-4 py-5"><p>Vue non trouvée.</p></div>';
                }
                ?>
                </div>
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
    <?php if (empty($trainingAdminNav) && ($showPortalFooter ?? true) && empty($usesAdminSidebarShell) && empty($isFormationWorkspace) && empty($isBackOfficeShell) && empty($isPlatformAdminShell) && empty($communityReelsPage) && empty($communityShowcasePage)): ?>
        <?php require base_path('views/partials/portal_footer.php'); ?>
    <?php endif; ?>
    <?php if (!empty($showBottomNav)): ?>
        <?php require base_path('views/partials/bottom_nav.php'); ?>
    <?php endif; ?>
    <?php require base_path('views/partials/community_report_modal.php'); ?>
    <?php require base_path('views/partials/portal_help_modal.php'); ?>
    <?php require base_path('views/partials/analytics_beacon.php'); ?>
    <?php require base_path('views/partials/cookie_banner.php'); ?>
    <?php require base_path('views/partials/demo_nda_session_widget.php'); ?>
    <?php if (!empty($isBackOfficeShell) && !empty(\App\Core\Session::get('user_id'))): ?>
        <?php require base_path('views/partials/ux_feedback_widget.php'); ?>
        <?php require base_path('views/partials/back_office_search.php'); ?>
    <?php endif; ?>
<?php
    $cdnPhase = 'body';
    $cdnPreset = 'portal';
    require base_path('views/partials/cdn_media_libs.php');
?>
    <script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/sw.js').catch(function () {});
        });
    }
    </script>
    <?php require base_path('views/partials/mirror_trap_link.php'); ?>
</body>
</html>
