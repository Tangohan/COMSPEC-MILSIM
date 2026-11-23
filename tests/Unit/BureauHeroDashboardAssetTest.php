<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class BureauHeroDashboardAssetTest extends TestCase
{
    public function testHeroIsGatedByRosterBureauModeAndSetsNoSecondH1(): void
    {
        $root = dirname(__DIR__, 2);
        $ctrl = (string) file_get_contents($root . '/app/Controllers/Admin/Organization/OrganizationDashboardController.php');
        $dash = (string) file_get_contents($root . '/views/admin/organization/dashboard.php');
        $hero = (string) file_get_contents($root . '/views/admin/organization/_bureau_hero.php');
        $css = (string) file_get_contents($root . '/public/assets/css/back-office-bureau-hero.css');

        self::assertStringContainsString('TenantTypeConfig::rosterBureauMode((string) ($tenantRow[\'tenant_type\']', $ctrl);
        self::assertStringContainsString("'bureauHero' => \$bureauHero", $ctrl);
        self::assertStringContainsString('$bureauHero ? [\'back-office-bureau-hero.css\'] : []', $ctrl);
        self::assertStringContainsString("if (!empty(\$bureauHero))", $dash);
        self::assertStringContainsString('_bureau_hero.php', $dash);

        self::assertStringContainsString('Ouvrir le Bureau des effectifs', $hero);
        self::assertStringContainsString('effectifs_workspace_url()', $hero);
        self::assertStringContainsString('data-pil-dismissable', $hero);
        self::assertStringContainsString('data-pil-dismiss>Masquer', $hero);
        self::assertStringContainsString('aria-hidden="true"', $hero);
        self::assertStringNotContainsString('<h1', $hero);
        self::assertStringNotContainsString('kicker', strtolower($hero . $css));
        self::assertStringNotContainsString('eyebrow', strtolower($hero . $css));
        self::assertStringContainsString('html[data-theme="dark"] .bhero', $css);
        self::assertStringContainsString('@media (max-width: 860px)', $css);
    }
}
