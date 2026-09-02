<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DashboardBandOrderAssetTest extends TestCase
{
    public function testDashboardBandsFlowDarkThenSteamThenLight(): void
    {
        $root = dirname(__DIR__, 2);
        $cc = (string) file_get_contents($root . '/views/partials/dashboard_command_center.php');
        $css = (string) file_get_contents($root . '/public/assets/css/dashboard-impact.css');

        $heroPos = strpos($cc, 'class="dash-hero"');
        $announcePos = strpos($cc, 'announce_tiles.php');
        $anomalyPos = strpos($cc, 'id="signaler-anomalie"');
        $adminPos = strpos($cc, 'id="contacter-admin-site"');
        $steamPos = strpos($cc, 'id="connexion-steam"');
        $doctrinePos = strpos($cc, 'dashboard_doctrine_pending.php');
        $miniPos = strpos($cc, 'dashboard_mini_articles.php');
        $hubPos = strpos($cc, 'dash-hub-stack');

        self::assertNotFalse($heroPos);
        self::assertNotFalse($announcePos);
        self::assertNotFalse($anomalyPos);
        self::assertNotFalse($adminPos);
        self::assertNotFalse($steamPos);
        self::assertNotFalse($doctrinePos);
        self::assertNotFalse($miniPos);
        self::assertNotFalse($hubPos);

        self::assertLessThan($announcePos, $heroPos);
        self::assertLessThan($anomalyPos, $announcePos);
        self::assertLessThan($adminPos, $anomalyPos);
        self::assertLessThan($steamPos, $adminPos);
        self::assertLessThan($doctrinePos, $steamPos);
        self::assertLessThan($miniPos, $doctrinePos);
        self::assertLessThan($hubPos, $miniPos);

        self::assertStringContainsString('if (!$dashSteamLinked)', $cc);
        self::assertStringContainsString('background: #fff', $css);
        self::assertStringContainsString('.dash-steam-tile', $css);
        self::assertStringContainsString('#123a5c', $css);
    }
}
