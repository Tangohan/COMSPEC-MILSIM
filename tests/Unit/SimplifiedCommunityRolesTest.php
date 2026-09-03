<?php

declare(strict_types=1);

use App\Services\Community\TenantDefaultRoleDefinitions;
use PHPUnit\Framework\TestCase;

final class SimplifiedCommunityRolesTest extends TestCase
{
    public function testCanonicalRoleLabelsAreExposed(): void
    {
        $roles = array_merge(TenantDefaultRoleDefinitions::governanceRoles(), TenantDefaultRoleDefinitions::operationalRoles());
        $labels = array_column($roles, 'name', 'slug');

        self::assertSame('Opérateur', $labels['member']);
        self::assertSame('Recrutement', $labels['recruiter']);
        self::assertSame('Ressources humaines', $labels['hr']);
        self::assertSame('Gestionnaire', $labels['community_owner']);
        self::assertSame('Gestionnaire adjoint', $labels['tenant_admin']);
        self::assertSame('Formateur', $labels['trainer']);
        self::assertSame('Responsable des formateurs', $labels['senior_instructor']);
        self::assertSame('Instructeur', $labels['instructor']);
        self::assertSame(TenantDefaultRoleDefinitions::allowedRoleSlugs(), array_keys($labels));
        self::assertSame([], TenantDefaultRoleDefinitions::organicStaffRoles());
    }

    public function testOnlyRequestedRolesHaveDefaultPermissions(): void
    {
        self::assertSame(
            TenantDefaultRoleDefinitions::allowedRoleSlugs(),
            array_keys(TenantDefaultRoleDefinitions::defaultPermissionSlugsForOperationalRoles())
        );
    }

    public function testRolePermissionsMatchPageResponsibilities(): void
    {
        $permissions = TenantDefaultRoleDefinitions::defaultPermissionSlugsForOperationalRoles();

        self::assertContains('training.view', $permissions['member']);
        self::assertContains('organization.recruitment.manage', $permissions['recruiter']);
        self::assertContains('organization.effectifs.hub.view', $permissions['hr']);
        self::assertContains('organization.recruitment.manage', $permissions['hr']);
        self::assertContains('training.create', $permissions['trainer']);
        self::assertContains('training.manage', $permissions['instructor']);
        self::assertContains('training.publish', $permissions['senior_instructor']);
    }
}
