<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DashboardIdentityStripPlacementAssetTest extends TestCase
{
    public function testIdentityStripSitsImmediatelyUnderDashboardHeader(): void
    {
        $root = dirname(__DIR__, 2);
        $cc = (string) file_get_contents($root . '/views/partials/dashboard_command_center.php');
        $header = (string) file_get_contents($root . '/views/partials/header_dashboard.php');
        $strip = (string) file_get_contents($root . '/views/partials/dashboard_idstrip.php');
        $css = (string) file_get_contents($root . '/public/assets/css/dashboard-impact.css');

        self::assertStringContainsString('athena_caverne_header.php', $header);
        self::assertStringContainsString('class="dash-idstrip"', $strip);
        self::assertStringContainsString('Identité opérationnelle', $strip);
        self::assertStringContainsString('Demande à l’encadrement', $strip);
        self::assertStringContainsString('Signaler une anomalie', $strip);

        $headerPos = strpos($cc, 'header_dashboard.php');
        $stripPos = strpos($cc, 'dashboard_idstrip.php');
        $heroPos = strpos($cc, 'class="dash-hero"');
        $hubPos = strpos($cc, 'dash-hub-stack');

        self::assertNotFalse($headerPos, 'Le layout dashboard doit inclure la navbar.');
        self::assertNotFalse($stripPos, 'Le layout dashboard doit inclure la barre d’identité.');
        self::assertNotFalse($heroPos, 'Le visuel de briefing doit rester après le chrome.');
        self::assertLessThan($stripPos, $headerPos);
        self::assertLessThan($heroPos, $stripPos);

        $between = substr($cc, $headerPos, $stripPos - $headerPos);
        self::assertStringNotContainsString('dash-hero', $between);
        self::assertStringNotContainsString('announce_tiles', $between);
        self::assertStringNotContainsString('dash-hub-stack', $between);

        self::assertMatchesRegularExpression(
            '/header_dashboard\.php[\'"]\);\s*require base_path\([\'"]views\/partials\/dashboard_idstrip\.php/',
            $cc
        );

        if ($hubPos !== false) {
            self::assertGreaterThan($heroPos, $hubPos);
        }

        self::assertMatchesRegularExpression(
            '/\.dash-idstrip\s*\{[^}]*position:\s*sticky/s',
            $css
        );
        self::assertStringContainsString('top: var(--athena-header-h, 4rem);', $css);
    }
}
