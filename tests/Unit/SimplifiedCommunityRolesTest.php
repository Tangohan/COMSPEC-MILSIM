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
        self::assertSame('Adjoint au commandement', $labels['deputy_commander']);
        self::assertSame('Formateur', $labels['trainer']);
        self::assertSame('Responsable des formateurs', $labels['senior_instructor']);
        self::assertSame('Instructeur', $labels['instructor']);
    }

    public function testRolePermissionsMatchPageResponsibilities(): void
    {
        $permissions = TenantDefaultRoleDefinitions::defaultPermissionSlugsForOperationalRoles();

        self::assertContains('training.view', $permissions['member']);
        foreach ([
            'personnel.profile.view',
            'personnel.progression.view',
            'operations.sitrep.view',
            'operations.sitrep.create',
            'operations.aar.view',
            'operations.readiness.view',
            'operations.medical.view',
            'operations.logistics.view',
            'operations.comms.view',
            'operations.doctrine.view',
            'doctrine.view',
            'media.view',
            'intel.transmission.view',
            'intel.transmission.contribute',
            'cooperation.missions.view',
            'cooperation.exchange.read',
            'cooperation.exchange.write',
            'cooperation.rex.submit',
            'cooperation.rex.read',
        ] as $permission) {
            self::assertContains($permission, $permissions['member'], $permission);
        }
        self::assertContains('organization.recruitment.manage', $permissions['recruiter']);
        self::assertContains('organization.effectifs.hub.view', $permissions['hr']);
        self::assertContains('organization.recruitment.manage', $permissions['hr']);
        self::assertContains('training.create', $permissions['trainer']);
        self::assertContains('training.manage', $permissions['instructor']);
        self::assertContains('training.publish', $permissions['senior_instructor']);
        self::assertContains('admin.organization', $permissions['community_owner']);
        self::assertContains('admin.organization', $permissions['tenant_admin']);
        self::assertTrue(\App\Authorization\PermissionImplication::isGranted(
            $permissions['community_owner'],
            'personnel.profile.update'
        ));
    }
}
