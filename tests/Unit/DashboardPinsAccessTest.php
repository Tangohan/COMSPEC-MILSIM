<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Authorization\DashboardPinsAccess;
use App\Core\Gate;
use PHPUnit\Framework\TestCase;

final class DashboardPinsAccessTest extends TestCase
{
    protected function tearDown(): void
    {
        Gate::getInstance()->setPermissions([]);
        parent::tearDown();
    }

    public function testOrganizationAdminCanManageWithoutPlatformAdmin(): void
    {
        $gate = Gate::getInstance();
        $gate->setPermissions(['admin.organization']);

        self::assertTrue(DashboardPinsAccess::canManage($gate));
        self::assertFalse($gate->allows('admin.system'));
        self::assertFalse($gate->allows('site.support'));
    }

    public function testTenantAccessAdminCanManageWithoutPlatformAdmin(): void
    {
        $gate = Gate::getInstance();
        $gate->setPermissions(['admin.access']);

        self::assertTrue(DashboardPinsAccess::canManage($gate));
        self::assertFalse($gate->allows('admin.system'));
    }

    public function testPinsManageSlugStillWorks(): void
    {
        $gate = Gate::getInstance();
        $gate->setPermissions(['dashboard.pins.manage']);

        self::assertTrue(DashboardPinsAccess::canManage($gate));
        self::assertFalse($gate->allows('admin.system'));
        self::assertFalse($gate->allows('admin.organization'));
    }

    public function testOrdinaryMemberCannotManage(): void
    {
        $gate = Gate::getInstance();
        $gate->setPermissions(['forum.view', 'documents.view']);

        self::assertFalse(DashboardPinsAccess::canManage($gate));
    }

    public function testSiteSupportDoesNotImplyDashboardPins(): void
    {
        $gate = Gate::getInstance();
        $gate->setPermissions(['site.support']);

        self::assertFalse(DashboardPinsAccess::canManage($gate));
    }
}
