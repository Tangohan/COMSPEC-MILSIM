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

        $heroPos = strpos($cc, 'class="dash-hero dash-reveal"');
        $actionsPos = strpos($cc, 'class="dash-action-band"');
        $announcePos = strpos($cc, 'announce_tiles.php');
        $anomalyPos = strpos($cc, 'id="signaler-anomalie"');
        $adminPos = strpos($cc, 'id="contacter-admin-site"');
        $steamPos = strpos($cc, 'id="connexion-steam"');
        $doctrinePos = strpos($cc, 'dashboard_doctrine_pending.php');
        $miniPos = strpos($cc, 'dashboard_mini_articles.php');
        $hubPos = strpos($cc, 'dash-hub-stack');

        self::assertNotFalse($heroPos);
        self::assertNotFalse($announcePos);
        self::assertNotFalse($actionsPos);
        self::assertNotFalse($anomalyPos);
        self::assertNotFalse($adminPos);
        self::assertNotFalse($steamPos);
        self::assertNotFalse($doctrinePos);
        self::assertNotFalse($miniPos);
        self::assertNotFalse($hubPos);

        // Redesign: the Steam link is now the first tile of the light "Actions du jour" band,
        // ahead of the anomaly and site-admin tiles.
        self::assertLessThan($announcePos, $heroPos);
        self::assertLessThan($actionsPos, $announcePos);
        self::assertLessThan($steamPos, $actionsPos);
        self::assertLessThan($anomalyPos, $steamPos);
        self::assertLessThan($adminPos, $anomalyPos);
        self::assertLessThan($doctrinePos, $adminPos);
        self::assertLessThan($miniPos, $doctrinePos);
        self::assertLessThan($hubPos, $miniPos);

        self::assertStringContainsString('if (!$dashSteamLinked)', $cc);
        self::assertStringContainsString('dash-action-tile dash-action-tile--steam', $cc);
        self::assertMatchesRegularExpression('/\.dash-action-tile,\s*a\.dash-action-tile\s*\{[^}]*background: #fff;/s', $css);
        self::assertMatchesRegularExpression('/\.dash-action-tile--steam\s*\{[^}]*border-left-color: #1b9ce5;/s', $css);
    }
}
