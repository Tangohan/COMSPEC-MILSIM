<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Personnel\UnitJobRoleSyncService;
use PHPUnit\Framework\TestCase;

final class UnitJobRoleSyncServiceTest extends TestCase
{
    public function testSlugForUnitIsStable(): void
    {
        self::assertSame('unit-12', UnitJobRoleSyncService::slugForUnit(12));
        self::assertSame('unit-0', UnitJobRoleSyncService::slugForUnit(-3));
    }

    public function testGenericAssignmentLabelsAreIgnored(): void
    {
        self::assertTrue(UnitJobRoleSyncService::isGenericAssignmentLabel(''));
        self::assertTrue(UnitJobRoleSyncService::isGenericAssignmentLabel('Membre'));
        self::assertTrue(UnitJobRoleSyncService::isGenericAssignmentLabel(' member '));
        self::assertFalse(UnitJobRoleSyncService::isGenericAssignmentLabel('Chef de groupe'));
        self::assertFalse(UnitJobRoleSyncService::isGenericAssignmentLabel('1er peloton'));
    }

    public function testCatalogSyncNoLongerWritesJobRoles(): void
    {
        $root = dirname(__DIR__, 2);
        $sync = (string) file_get_contents($root . '/app/Services/Rbac/MilitaryRoleCatalogSyncService.php');
        $boot = (string) file_get_contents($root . '/app/Services/Personnel/PersonnelJobRoleBootstrapService.php');
        $migrate = (string) file_get_contents($root . '/bootstrap/personnel_job_roles_migration.php');
        $run = (string) file_get_contents($root . '/run-migrations.php');

        self::assertStringNotContainsString('INSERT INTO personnel_job_roles', $sync);
        self::assertStringContainsString('UnitJobRoleSyncService', $boot);
        self::assertStringNotContainsString('MilitaryRoleCatalogSyncService', $boot);
        self::assertStringNotContainsString('ensureDefaultsForTenant', $migrate);
        self::assertStringNotContainsString('MilitaryRoleCatalogSyncService::syncAllTenants', $run);
        self::assertStringContainsString('unit_derived_job_roles_migration.php', $run);
        self::assertStringContainsString('community_access_roles_purge_v1_migration.php', $run);
        $purge = (string) file_get_contents($root . '/app/Services/Personnel/UnitJobRoleSyncService.php');
        self::assertStringContainsString('MilitaryOperationalRoleCatalog::catalogSlugSet', $purge);
    }
}
