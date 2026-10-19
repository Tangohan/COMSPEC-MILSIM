<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PlatformAdminSkinAssetTest extends TestCase
{
    public function testEveryPlatformPageGetsTheCommonSkin(): void
    {
        $root = dirname(__DIR__, 2);
        $layout = (string) file_get_contents($root . '/views/layout/main.php');
        $css = (string) file_get_contents($root . '/public/assets/css/platform-admin-skin.css');

        self::assertStringContainsString("['platform-admin.css'], \$backOfficePageCss, ['platform-admin-skin.css']", $layout);
        self::assertStringContainsString('<div class="pa-skin">', $layout);
        self::assertStringContainsString('.pa-skin .pa-head', $css);
        self::assertStringContainsString('.pa-skin > :not(.pa)', $css);

        // Chaque page plateforme écrite en utilitaires marque son en-tête pour le bandeau commun.
        // Les pages refondues sur la coque « .pa » (bandeau pa-hero) en sont exemptées : le skin
        // ne s'applique qu'aux enfants :not(.pa).
        $paShell = ['dashboard', 'mail_traffic', 'name_filter', 'plan_gifts', 'platform_review_index', 'tenant_recovery', 'tenants_index', 'tenants_plan_form', 'users', 'ux_feedback_index'];
        foreach (glob($root . '/views/admin/system/*.php') ?: [] as $file) {
            $view = (string) file_get_contents($file);
            if (!str_contains($view, '<h1')) {
                continue;
            }
            if (in_array(basename($file, '.php'), $paShell, true)) {
                self::assertMatchesRegularExpression('/<div class="pa[ "]/', $view, basename($file));
                self::assertStringContainsString('pa-hero__title', $view, basename($file));
                continue;
            }
            self::assertStringContainsString('pa-head', $view, basename($file));
        }
    }

    public function testPlatformMenuIsGroupedFilterableAndComplete(): void
    {
        $root = dirname(__DIR__, 2);
        $sidebar = (string) file_get_contents($root . '/views/partials/platform_admin_sidebar.php');
        $topbar = (string) file_get_contents($root . '/views/partials/back_office_topbar.php');
        $js = (string) file_get_contents($root . '/public/assets/js/back-office-sidebar.js');

        self::assertStringContainsString('id="ath-menu-search"', $sidebar);
        self::assertStringContainsString('data-ath-desc', $sidebar);
        self::assertStringContainsString("data-ath-desc", $js);
        self::assertStringContainsString("'admin/system/deployment/campaigns'", $sidebar);
        // Les alertes plateforme sont ouvertes à l’assistance (PlatformHubMiddleware) : le menu suit la route.
        self::assertMatchesRegularExpression("/'admin\\/system\\/alerts'[^\\]]*'access' => 'hub'/", $sidebar);
        self::assertStringContainsString('$platformAdminCrumbGroup', $sidebar);
        self::assertStringContainsString('$platformAdminCrumbGroup', $topbar);
    }

    public function testOrphanCommunityToolsAreReachableFromTheBackOffice(): void
    {
        $root = dirname(__DIR__, 2);
        $nav = (string) file_get_contents($root . '/views/partials/ath_sidebar_nav.php');
        $platformNav = (string) file_get_contents($root . '/views/partials/platform_admin_sidebar.php');
        $pages = (string) file_get_contents($root . '/config/back_office_pages.php');

        foreach (['back-office/ressources/atak-realism/config', 'back-office/ressources/atak-mod-blocks', 'back-office/ressources/atak-diffusion-rapports', 'back-office/ressources/forum-config', 'back-office/content-moderation'] as $path) {
            self::assertStringContainsString("url('" . $path . "')", $nav, $path);
        }
        foreach (['back-office/ressources/atak-mod-blocks', 'back-office/ressources/atak-diffusion-rapports', 'back-office/ressources/atak-realism'] as $path) {
            self::assertStringContainsString("'path' => '" . $path . "'", $pages, $path);
        }
        // Données de toutes les communautés : administration plateforme seulement, jamais dans le back-office.
        foreach (['admin/atak-beta', 'admin/atak-mod-reports'] as $path) {
            self::assertStringNotContainsString($path, $nav, $path);
            self::assertStringContainsString("'path' => '" . $path . "'", $platformNav, $path);
        }
        self::assertStringContainsString('back-office-atak-routing.css', $pages);
        self::assertFileExists($root . '/public/assets/css/back-office-atak-routing.css');
    }
}
