<?php

declare(strict_types=1);

use App\Services\Admin\RolePermissionService;
use App\Services\Rbac\CommunityAccessProfiles;
use App\Support\EffectifsLmsAccess;
use App\Core\Gate;
use PHPUnit\Framework\TestCase;

final class ManagerProtectedPermissionsTest extends TestCase
{
    public function testManagerProtectedSlugsCoverOrganizationAndRoles(): void
    {
        $slugs = CommunityAccessProfiles::managerProtectedPermissionSlugs();
        self::assertContains('admin.organization', $slugs);
        self::assertContains('admin.roles.manage', $slugs);
        self::assertContains(
            'admin.organization',
            CommunityAccessProfiles::permissionSlugsFor(CommunityAccessProfiles::MANAGER)
        );
    }

    public function testWithManagerProtectedPermissionsReinjectsMissingIds(): void
    {
        $permissions = $this->createMock(\App\Repositories\PermissionRepository::class);
        $permissions->method('allForTenant')->willReturn([
            ['id' => 10, 'slug' => 'admin.organization'],
            ['id' => 11, 'slug' => 'admin.roles.manage'],
            ['id' => 12, 'slug' => 'documents.view'],
        ]);
        $roles = $this->createMock(\App\Repositories\RoleRepository::class);
        $svc = new RolePermissionService($roles, $permissions);

        $kept = $svc->withManagerProtectedPermissions(1, CommunityAccessProfiles::SLUG_MANAGER, [12]);
        self::assertContains(10, $kept);
        self::assertContains(11, $kept);
        self::assertContains(12, $kept);

        $untouched = $svc->withManagerProtectedPermissions(1, CommunityAccessProfiles::SLUG_MEMBER, [12]);
        self::assertSame([12], $untouched);
    }

    public function testEffectifsAllowsRoleManagersToReachAccessPage(): void
    {
        $gate = Gate::getInstance();
        $gate->setPermissions(['admin.roles.manage']);
        self::assertTrue(EffectifsLmsAccess::allows($gate));
        self::assertTrue(EffectifsLmsAccess::canManageRoles($gate));
        $gate->setPermissions([]);
    }
}
