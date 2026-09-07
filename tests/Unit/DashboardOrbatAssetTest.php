<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DashboardOrbatAssetTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testDashboardOrbatIsWiredWithPortraitsAndExpandControls(): void
    {
        $root = $this->root();
        $support = (string) file_get_contents($root . '/app/Support/DashboardOrbatTree.php');
        $partial = (string) file_get_contents($root . '/views/partials/dashboard_orbat.php');
        $css = (string) file_get_contents($root . '/public/assets/css/dashboard-orbat.css');
        $home = (string) file_get_contents($root . '/app/Controllers/Web/HomeController.php');
        $command = (string) file_get_contents($root . '/views/partials/dashboard_command_center.php');
        $dash = (string) file_get_contents($root . '/views/dashboard.php');

        self::assertStringContainsString('DashboardOrbatTree', $support);
        self::assertStringContainsString('batchPortraits', $support);
        self::assertStringContainsString('character_portrait_path', $support);
        self::assertStringContainsString('OrbatRosterPayload::buildForTenant', $support);

        self::assertStringContainsString('dash-orbat', $partial);
        self::assertStringContainsString('Chaîne de commandement', $partial);
        self::assertStringContainsString('data-dash-orbat-expand', $partial);
        self::assertStringContainsString('dash-orbat__avatar', $partial);
        self::assertStringContainsString('<details', $partial);

        self::assertStringContainsString('.dash-orbat__person--lead', $css);
        self::assertStringContainsString('border-radius: 999px', $css);
        self::assertStringNotContainsString('purple', strtolower($css));

        self::assertStringContainsString('DashboardOrbatTree::buildForTenant', $home);
        self::assertStringContainsString("'dashboard_orbat'", $home);
        self::assertStringContainsString('dashboard_orbat.php', $command);
        self::assertStringContainsString('dashboard-orbat.css', $dash);
    }
}
