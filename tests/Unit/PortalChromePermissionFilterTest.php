<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PortalChromePermissionFilterTest extends TestCase
{
    public function testAllMemberChromeUsesTheSharedPermissionFilter(): void
    {
        $root = dirname(__DIR__, 2);
        $helpers = (string) file_get_contents($root . '/app/Support/helpers.php');
        $header = (string) file_get_contents($root . '/views/partials/athena_caverne_header.php');
        $dashboardRail = (string) file_get_contents($root . '/views/partials/dashboard_aside.php');

        self::assertStringContainsString('function portal_nav_href_permission_allowed', $helpers);
        self::assertStringContainsString('TenantTypeConfig::uriAllowed', $helpers);
        self::assertStringContainsString('portal_nav_href_permission_allowed(url($path))', $header);
        self::assertStringContainsString('portal_nav_href_permission_allowed((string) $item[\'href\'])', $dashboardRail);
    }
}
