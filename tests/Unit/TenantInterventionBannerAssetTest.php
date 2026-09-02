<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class TenantInterventionBannerAssetTest extends TestCase
{
    public function testStandaloneShellsIncludeInterventionBannerAtBodyStart(): void
    {
        $root = dirname(__DIR__, 2);
        $banner = (string) file_get_contents($root . '/views/partials/tenant_intervention_banner.php');
        $layout = (string) file_get_contents($root . '/views/layout/main.php');
        $dashboard = (string) file_get_contents($root . '/views/dashboard.php');
        $effectifs = (string) file_get_contents($root . '/views/dashboard_effectifs.php');
        $atakDash = (string) file_get_contents($root . '/views/dashboard_atak.php');
        $forum = (string) file_get_contents($root . '/views/layout/forum.php');
        $atak = (string) file_get_contents($root . '/views/atak.php');

        self::assertStringContainsString('tenant-intervention-banner', $banner);
        self::assertStringContainsString('Administration de l’organisation', $banner);
        self::assertStringNotContainsString('MODE ADMINISTRATION TENANT', $banner);
        self::assertStringContainsString('Quitter l’organisation', $banner);

        foreach ([$layout, $dashboard, $effectifs, $atakDash, $forum, $atak] as $view) {
            self::assertStringContainsString('views/partials/tenant_intervention_banner.php', $view);
        }

        $this->assertBannerBeforeHeader($dashboard, 'header_dashboard.php');
        $this->assertBannerBeforeHeader($effectifs, 'header_dashboard.php');
        $this->assertBannerBeforeHeader($atakDash, 'header_dashboard.php');
        $this->assertBannerBeforeHeader($forum, 'header_portal.php');
        $this->assertBannerBeforeHeader($layout, 'header_portal.php');
    }

    private function assertBannerBeforeHeader(string $view, string $headerFile): void
    {
        $bannerPos = strpos($view, 'tenant_intervention_banner.php');
        $headerPos = strpos($view, $headerFile);
        self::assertNotFalse($bannerPos, 'Le bandeau d’intervention doit être inclus.');
        self::assertNotFalse($headerPos, 'L’en-tête de page doit rester présent.');
        self::assertLessThan($headerPos, $bannerPos);
    }
}
