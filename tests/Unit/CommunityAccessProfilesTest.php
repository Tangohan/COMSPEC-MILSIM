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
            self::assertContains('forum.view', $slugs);
        }
        $hr = CommunityAccessProfiles::permissionSlugsFor(CommunityAccessProfiles::HR);
        self::assertContains('organization.effectifs.hub.view', $hr);
        self::assertContains('personnel.profile.update', $hr);
        self::assertNotContains('admin.organization', $hr);

        $manager = CommunityAccessProfiles::permissionSlugsFor(CommunityAccessProfiles::MANAGER);
        self::assertContains('admin.organization', $manager);
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
        self::assertFalse(CommunityAccessCollapseService::mayCreateTenantRoleSlug('tenant_admin'));
        self::assertFalse(CommunityAccessCollapseService::mayCreateTenantRoleSlug('atak_operator'));
        self::assertFalse(CommunityAccessCollapseService::mayCreateTenantRoleSlug('infantry_rifleman'));
    }
}
