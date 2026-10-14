<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\PortalAccessChoice;
use PHPUnit\Framework\TestCase;

final class NoOrganizationNavigationTest extends TestCase
{
    public function testUsefulPagesStayVisibleWithoutOrganization(): void
    {
        foreach (['dashboard', 'forum', 'forum/sujet/12', 'communities', 'communities/create', 'join', 'personnel/me', 'account', 'account/banner'] as $path) {
            self::assertTrue(PortalAccessChoice::noOrganizationAllowsPath($path), $path);
        }
    }

    public function testEmptyTenantPagesAreHiddenWithoutOrganization(): void
    {
        foreach (['hub', 'formations', 'formations/mes-formations', 'personnel', 'back-office', 'evenements', 'atak', 'orbat', 'documents', 'jnet', 'joinery', 'dashboards'] as $path) {
            self::assertFalse(PortalAccessChoice::noOrganizationAllowsPath($path), $path);
        }
    }

    public function testHeaderAppliesTheRuleAndOffersCommunityCreation(): void
    {
        $root = dirname(__DIR__, 2);
        $header = (string) file_get_contents($root . '/views/partials/athena_caverne_header.php');
        self::assertStringContainsString('restrictsNavigationToNoOrganization()', $header);
        self::assertStringContainsString('noOrganizationAllowsPath($path)', $header);
        self::assertStringContainsString("url('communities/create')", $header);

        $dash = (string) file_get_contents($root . '/views/dashboard.php');
        self::assertStringContainsString("url('communities/create')", $dash);
        self::assertStringContainsString("url('communities/create/guide')", $dash);
    }
}
