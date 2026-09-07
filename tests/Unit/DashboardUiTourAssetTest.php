<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DashboardUiTourAssetTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testTourIsVisualDismissableAndStored(): void
    {
        $root = $this->root();
        $js = (string) file_get_contents($root . '/public/assets/js/dashboard-ui-tour.js');
        $css = (string) file_get_contents($root . '/public/assets/css/dashboard-ui-tour.css');
        $boot = (string) file_get_contents($root . '/views/partials/dashboard_ui_tour_boot.php');
        $header = (string) file_get_contents($root . '/views/partials/athena_caverne_header.php');
        $cc = (string) file_get_contents($root . '/views/partials/dashboard_command_center.php');
        $dash = (string) file_get_contents($root . '/views/dashboard.php');
        $home = (string) file_get_contents($root . '/app/Controllers/Web/HomeController.php');
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $mig = (string) file_get_contents($root . '/bootstrap/user_ui_tours_migration.php');
        $run = (string) file_get_contents($root . '/run-migrations.php');
        $ctrl = (string) file_get_contents($root . '/app/Controllers/Api/UiTourController.php');

        self::assertStringContainsString('Masquer le guide', $js);
        self::assertStringContainsString('dash-tour-nav', $js);
        self::assertStringContainsString('api/ui-tours/dismiss', $js);
        self::assertStringContainsString('tour_key', $js);
        self::assertStringNotContainsString('localStorage', $js);

        self::assertStringContainsString('dash-tour__spotlight', $css);
        self::assertStringContainsString('athena-header__guide-btn', $css);

        self::assertStringContainsString('dashboard-ui-tour.js', $boot);
        self::assertStringContainsString('dashboard_ui_tour_boot.php', $dash);
        self::assertStringContainsString('dashboard_ui_tour_boot.php', (string) file_get_contents($root . '/views/dashboard_atak.php'));
        self::assertStringContainsString('dashboard_ui_tour_boot.php', (string) file_get_contents($root . '/views/dashboard_effectifs.php'));
        self::assertStringContainsString('user_ui_tours', (string) file_get_contents($root . '/app/Services/Account/AccountPurgeService.php'));
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `user_ui_tours`', (string) file_get_contents($root . '/migrations/schema.sql'));
        self::assertStringContainsString('athena-header__guide-btn', (string) file_get_contents($root . '/public/assets/css/athena-header.css'));

        self::assertStringContainsString('id="dash-tour-nav"', $header);
        self::assertStringContainsString('id="dash-tour-start"', $header);
        self::assertStringContainsString('Guide', $header);

        self::assertStringContainsString('id="dash-tour-hero"', $cc);
        self::assertStringContainsString('id="dash-tour-formations"', $cc);
        self::assertStringContainsString('id="dash-tour-activity"', $cc);
        self::assertStringContainsString('id="dash-tour-identity"', (string) file_get_contents($root . '/views/partials/dashboard_idstrip.php'));
        self::assertStringContainsString('id="dash-rail"', (string) file_get_contents($root . '/views/partials/dashboard_aside.php'));

        self::assertStringContainsString('dashboardUiTourPayload', $home);
        self::assertStringContainsString('dashboard_ui_tour', $home);
        self::assertStringContainsString("post('/api/ui-tours/dismiss'", $routes);
        self::assertStringContainsString('CREATE TABLE user_ui_tours', $mig);
        self::assertStringContainsString('run_user_ui_tours_migration', $run);
        self::assertStringContainsString("tour_key !== UserUiTourRepository::KEY_DASHBOARD", $ctrl);
        self::assertStringNotContainsString('Cette annonce ne peut pas être masquée', $ctrl);
    }
}
