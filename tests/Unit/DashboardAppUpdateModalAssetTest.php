<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DashboardAppUpdateModalAssetTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testDashboardShellsReuseExistingUpdateCheck(): void
    {
        $root = $this->root();
        $partial = (string) file_get_contents($root . '/views/partials/app_update_check.php');
        $dashboard = (string) file_get_contents($root . '/views/dashboard.php');
        $header = (string) file_get_contents($root . '/views/partials/header_dashboard.php');
        $atakDash = (string) file_get_contents($root . '/views/dashboard_atak.php');
        $effectifsDash = (string) file_get_contents($root . '/views/dashboard_effectifs.php');
        $layout = (string) file_get_contents($root . '/views/layout/main.php');
        $atak = (string) file_get_contents($root . '/views/atak.php');
        $js = (string) file_get_contents($root . '/public/assets/js/app-version-check.js');

        self::assertFileExists($root . '/public/assets/css/app-update-modal.css');
        self::assertFileExists($root . '/public/assets/js/app-version-check.js');

        self::assertStringContainsString('app-update-modal.css', $partial);
        self::assertStringContainsString('platform_app_version()', $partial);
        self::assertStringContainsString('assets/js/app-version-check.js', $partial);
        self::assertStringContainsString('athena_app_update_check_included', $partial);

        self::assertStringContainsString('views/partials/app_update_check.php', $dashboard);
        self::assertStringContainsString('views/partials/app_update_check.php', $header);
        self::assertStringContainsString('header_dashboard.php', $atakDash);
        self::assertStringContainsString('header_dashboard.php', $effectifsDash);

        self::assertStringContainsString('assets/js/app-version-check.js', $layout);
        self::assertStringContainsString('app-update-modal.css', $layout);
        self::assertStringContainsString('app-version-check.js', $atak);

        self::assertStringContainsString('Mise à jour', $js);
        self::assertStringContainsString('preview_update_modal', $js);
        self::assertStringContainsString('Plus tard', $js);
        self::assertStringContainsString('Actualiser', $js);
        self::assertStringContainsString('Une nouvelle version du portail est disponible', $js);
    }
}
