<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DashboardSteamConnectTileAssetTest extends TestCase
{
    public function testDashboardOffersASteamConnectTileToSteam(): void
    {
        $root = dirname(__DIR__, 2);
        $cc = (string) file_get_contents($root . '/views/partials/dashboard_command_center.php');
        $aside = (string) file_get_contents($root . '/views/partials/dashboard_aside.php');
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $css = (string) file_get_contents($root . '/public/assets/css/dashboard-impact.css');
        $ctrl = (string) file_get_contents($root . '/app/Controllers/Web/AccountController.php');
        $prefs = (string) file_get_contents($root . '/views/account/preferences.php');

        self::assertStringContainsString('id="connexion-steam"', $cc);
        self::assertStringContainsString('if (!$dashSteamLinked)', $cc);
        self::assertStringContainsString('Connexion Steam', $cc);
        self::assertStringContainsString("url('account/steam/connect')", $cc);
        self::assertStringContainsString('Se connecter avec Steam', $cc);
        self::assertStringContainsString('$acctSteamLinked', $aside);
        self::assertStringContainsString("url('account/steam/connect')", $aside);
        self::assertStringContainsString("url('account/steam/connect')", $prefs);
        self::assertStringContainsString("/account/steam/connect", $routes);
        self::assertStringContainsString("/account/steam/callback", $routes);
        self::assertStringContainsString('function steamConnect', $ctrl);
        self::assertStringContainsString('function steamCallback', $ctrl);
        self::assertStringContainsString('dash-steam-tile', $css);
        self::assertStringNotContainsString('openid', strtolower($cc));
        self::assertStringNotContainsString('/api/', $cc);
    }
}
