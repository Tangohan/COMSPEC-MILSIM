<?php

declare(strict_types=1);

use App\Authorization\SystemReservedPermissions;
use App\Services\Personnel\PersonnelDutyPositionService;
use App\Services\Rbac\CommunityAccessCollapseService;
use App\Services\Rbac\CommunityAccessProfiles;
use PHPUnit\Framework\TestCase;

final class CommunityAccessProfilesTest extends TestCase
{
    public function testThreeProfilesOnly(): void
    {
        $defs = CommunityAccessProfiles::definitions();
        self::assertCount(3, $defs);
        $labels = array_column($defs, 'name', 'key');
        self::assertSame('Membre', $labels[CommunityAccessProfiles::MEMBER]);
        self::assertSame('Ressources humaines', $labels[CommunityAccessProfiles::HR]);
        self::assertSame('Gestionnaire', $labels[CommunityAccessProfiles::MANAGER]);
    }

    public function testLegacySlugsMapToManagerThenHrThenMember(): void
    {
        self::assertSame(CommunityAccessProfiles::MANAGER, CommunityAccessProfiles::resolveFromSlugs(['member', 'tenant_admin']));
        self::assertSame(CommunityAccessProfiles::HR, CommunityAccessProfiles::resolveFromSlugs(['member', 'recruiter']));
        self::assertSame(CommunityAccessProfiles::MEMBER, CommunityAccessProfiles::resolveFromSlugs(['instructor', 'medic']));
        self::assertSame(CommunityAccessProfiles::MEMBER, CommunityAccessProfiles::resolveFromSlugs([]));
    }

    public function testPermissionPacksStayClearOfPlatformReservedSlugs(): void
    {
        foreach (CommunityAccessProfiles::keys() as $key) {
            $slugs = CommunityAccessProfiles::permissionSlugsFor($key);
            self::assertSame($slugs, SystemReservedPermissions::filter($slugs));
            self::assertContains('documents.view', $slugs);
        }
        $member = CommunityAccessProfiles::permissionSlugsFor(CommunityAccessProfiles::MEMBER);
        self::assertContains('admin.backoffice.view', $member);
        self::assertContains('atak.terminals.view', $member);
        self::assertContains('operations.sitrep.create', $member);
        self::assertContains('intel.transmission.contribute', $member);
        self::assertContains('media.view', $member);
        self::assertNotContains('admin.organization', $member);
        self::assertNotContains('atak.terminals.manage', $member);
        self::assertNotContains('atak.sse.access', $member);
        self::assertNotContains('finances.view', $member);

        $hr = CommunityAccessProfiles::permissionSlugsFor(CommunityAccessProfiles::HR);
        self::assertContains('organization.effectifs.hub.view', $hr);
        self::assertContains('personnel.profile.update', $hr);
        self::assertContains('admin.backoffice.view', $hr);
        self::assertContains('atak.terminals.view', $hr);
        self::assertNotContains('admin.organization', $hr);

        $manager = CommunityAccessProfiles::permissionSlugsFor(CommunityAccessProfiles::MANAGER);
        self::assertContains('admin.organization', $manager);
        foreach (CommunityAccessProfiles::managerProtectedPermissionSlugs() as $protected) {
            self::assertContains($protected, $manager);
        }
    }

    public function testRetainedTenantSlugsAreAccessPlusDuty(): void
    {
        $kept = CommunityAccessCollapseService::retainedTenantSlugs();
        self::assertContains('community_owner', $kept);
        self::assertContains('hr', $kept);
        self::assertContains('member', $kept);
        self::assertContains(PersonnelDutyPositionService::SLUG_TRAINING, $kept);
        self::assertContains(PersonnelDutyPositionService::SLUG_ACTIVE, $kept);
        self::assertCount(5, $kept);
        self::assertTrue(CommunityAccessCollapseService::mayCreateTenantRoleSlug('member'));
        self::assertTrue(CommunityAccessCollapseService::mayCreateTenantRoleSlug('status_active_duty'));
        self::assertTrue(CommunityAccessCollapseService::mayCreateTenantRoleSlug('access-custom-recrutement'));
        self::assertFalse(CommunityAccessCollapseService::mayCreateTenantRoleSlug('tenant_admin'));
        self::assertFalse(CommunityAccessCollapseService::mayCreateTenantRoleSlug('atak_operator'));
        self::assertFalse(CommunityAccessCollapseService::mayCreateTenantRoleSlug('infantry_rifleman'));
    }
    /** Tout le monde porte Membre : le niveau affiché est le plus élevé tenu, pas le premier de la liste. */
    public function testCurrentAccessRoleIdPrefersHighestHeldLevel(): void
    {
        $roles = [
            ['id' => 10, 'slug' => 'member'],
            ['id' => 11, 'slug' => 'hr'],
            ['id' => 12, 'slug' => 'community_owner'],
            ['id' => 20, 'slug' => 'access-custom-personnel-a'],
        ];
        self::assertSame(20, CommunityAccessProfiles::currentAccessRoleId($roles, [10, 20]));
        self::assertSame(11, CommunityAccessProfiles::currentAccessRoleId($roles, [10, 11]));
        self::assertSame(12, CommunityAccessProfiles::currentAccessRoleId($roles, [10, 20, 12]));
        self::assertSame(10, CommunityAccessProfiles::currentAccessRoleId($roles, [10, 99]));
        self::assertSame(0, CommunityAccessProfiles::currentAccessRoleId($roles, [99]));
    }

    public function testOnlyHrPresetCanBeHidden(): void
    {
        self::assertTrue(CommunityAccessProfiles::isHideablePresetSlug('hr'));
        self::assertFalse(CommunityAccessProfiles::isHideablePresetSlug('member'));
        self::assertFalse(CommunityAccessProfiles::isHideablePresetSlug('community_owner'));
        self::assertFalse(CommunityAccessProfiles::isHideablePresetSlug('access-custom-x'));
    }
}
