<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Authorization\SystemReservedPermissions;
use App\Services\AccessControl\FunctionBasedAccessCatalog;
use PHPUnit\Framework\TestCase;

final class FunctionBasedAccessCatalogTest extends TestCase
{
    public function testCatalogueContainsOnlyTheSevenClassicAccessRoles(): void
    {
        FunctionBasedAccessCatalog::assertInvariants();
        self::assertSame(
            ['Gestionnaire', 'Gestionnaire adjoint', 'Formateur', 'Recruteur', 'Responsable ressources humaines', 'Adjoint responsable ressources humaines', 'Membre'],
            array_column(FunctionBasedAccessCatalog::roles(), 'label')
        );
        self::assertCount(7, FunctionBasedAccessCatalog::accessRoleSlugs());
    }

    public function testDeputiesHaveExactlyTheirManagersPermissions(): void
    {
        $roles = FunctionBasedAccessCatalog::roles();
        self::assertSame($roles['manager']['permission_slugs'], $roles['deputy_manager']['permission_slugs']);
        self::assertSame($roles['hr_manager']['permission_slugs'], $roles['deputy_hr_manager']['permission_slugs']);
    }

    public function testNoAccessRoleCanCarryAPlatformOrWildcardGrant(): void
    {
        foreach (FunctionBasedAccessCatalog::roles() as $role) {
            foreach ($role['permission_slugs'] as $permission) {
                self::assertFalse(SystemReservedPermissions::isReserved($permission));
                self::assertNotContains($permission, ['admin.access', 'admin.organization']);
            }
        }
    }

    public function testMilitaryLabelsAreNotAccessRoles(): void
    {
        self::assertSame([], array_intersect(
            ['jtac', 'pj', 'operator', 'combat_controller', 'crew_chief'],
            FunctionBasedAccessCatalog::accessRoleSlugs()
        ));
    }
}
