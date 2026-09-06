<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Gate;
use App\Services\Rbac\PlatformAdminFlag;
use PHPUnit\Framework\TestCase;

final class PlatformAdminFlagTest extends TestCase
{
    protected function tearDown(): void
    {
        Gate::getInstance()->setPermissions([]);
        parent::tearDown();
    }

    public function testUserRowFlagIsTheOnlySource(): void
    {
        self::assertTrue(PlatformAdminFlag::isEnabled(['is_platform_admin' => 1]));
        self::assertTrue(PlatformAdminFlag::isEnabled(['is_super_admin' => 1]));
        self::assertFalse(PlatformAdminFlag::isEnabled(['is_platform_admin' => 0]));
        self::assertFalse(PlatformAdminFlag::isEnabled([]));
    }

    public function testSiteRolesCannotGrantPlatformAdmin(): void
    {
        self::assertSame(
            ['site.support', 'forum.moderate'],
            PlatformAdminFlag::stripRoleGrants(['admin.system', 'site.support', '*', 'forum.moderate'])
        );
    }

    public function testMergeInjectsAdminSystemOnlyFromTheFlag(): void
    {
        self::assertSame(
            ['forum.moderate'],
            PlatformAdminFlag::mergeIntoPermissions(['forum.moderate', 'admin.system'], false)
        );
        self::assertSame(
            ['forum.moderate', 'admin.system'],
            PlatformAdminFlag::mergeIntoPermissions(['forum.moderate'], true)
        );
    }

    public function testGateFlagOpensTheWholeSite(): void
    {
        $gate = Gate::getInstance();
        $gate->setPermissions(['dashboard.view']);
        self::assertFalse($gate->allows('admin.system'));
        $gate->setPlatformAdmin(true);
        self::assertTrue($gate->isPlatformAdmin());
        self::assertTrue($gate->allows('admin.system'));
        self::assertTrue($gate->allows('admin.organization'));
    }

    public function testSetPermissionsClearsTheFlag(): void
    {
        $gate = Gate::getInstance();
        $gate->setPlatformAdmin(true);
        $gate->setPermissions(['dashboard.view']);
        self::assertFalse($gate->isPlatformAdmin());
        self::assertFalse($gate->allows('admin.system'));
    }

    public function testConfirmationPhrasesMustBeCopied(): void
    {
        self::assertTrue(PlatformAdminFlag::confirmMatches('ouvrir le site', PlatformAdminFlag::CONFIRM_GRANT));
        self::assertTrue(PlatformAdminFlag::confirmMatches('RETIRER L ACCES', PlatformAdminFlag::CONFIRM_REVOKE));
        self::assertFalse(PlatformAdminFlag::confirmMatches('oui', PlatformAdminFlag::CONFIRM_GRANT));
    }
}
