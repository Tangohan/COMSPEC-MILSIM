<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CommunityAccessCollapseAssetTest extends TestCase
{
    public function testCollapseDeletesLeftoverTenantRolesAndClearsJobPermissions(): void
    {
        $root = dirname(__DIR__, 2);
        $collapse = (string) file_get_contents($root . '/app/Services/Rbac/CommunityAccessCollapseService.php');
        $bootstrap = (string) file_get_contents($root . '/app/Services/Community/TenantBootstrapService.php');
        $jobs = (string) file_get_contents($root . '/app/Repositories/PersonnelJobRoleRepository.php');
        $run = (string) file_get_contents($root . '/run-migrations.php');

        self::assertStringContainsString('DELETE FROM roles WHERE tenant_id = ? AND id IN', $collapse);
        self::assertStringContainsString('personnel_job_role_permissions', $collapse);
        self::assertStringNotContainsString('Les anciens rôles restent en base', $collapse);

        self::assertStringNotContainsString("'tenant_admin'", $bootstrap);
        self::assertStringContainsString('ensureAccessProfilesForTenant', $bootstrap);
        self::assertStringContainsString('CommunityAccessCollapseService', $bootstrap);

        self::assertStringNotContainsString('INSERT INTO personnel_job_role_permissions', $jobs);
        self::assertStringContainsString('community_access_roles_purge_v1_migration.php', $run);
        self::assertStringContainsString('member_backoffice_atak_view_migration.php', $run);
        self::assertStringContainsString('member_operator_daily_rights_migration.php', $run);
    }
}
