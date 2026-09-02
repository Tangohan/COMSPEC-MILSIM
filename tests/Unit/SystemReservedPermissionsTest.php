<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Authorization\PermissionImplication;
use App\Authorization\SystemReservedPermissions;
use App\Authorization\TenantPermissionCatalog;
use App\Services\Rbac\RolePermissionMatrixCatalog;
use PHPUnit\Framework\TestCase;

/**
 * Verrou de régression : aucune habilitation réservée à la plateforme ne doit pouvoir
 * atteindre un rôle de communauté, par quelque source que ce soit.
 *
 * Ces tests échouent si quelqu’un ajoute un slug plateforme au catalogue tenant, à un
 * profil automatique ou à la matrice des rôles.
 */
final class SystemReservedPermissionsTest extends TestCase
{
    public function testReservedSlugsAreRecognised(): void
    {
        self::assertTrue(SystemReservedPermissions::isReserved('admin.system'));
        self::assertTrue(SystemReservedPermissions::isReserved('*'));
        self::assertTrue(SystemReservedPermissions::isReserved('site.support'));
        self::assertTrue(SystemReservedPermissions::isReserved('site.tenants.manage'));
        self::assertTrue(SystemReservedPermissions::isReserved('platform.billing.manage'));
        self::assertTrue(SystemReservedPermissions::isReserved('system.maintenance'));
        self::assertTrue(SystemReservedPermissions::isReserved('admin.system.updates'));
    }

    public function testReservedDetectionIsCaseAndSpaceInsensitive(): void
    {
        self::assertTrue(SystemReservedPermissions::isReserved('  Admin.System  '));
        self::assertTrue(SystemReservedPermissions::isReserved('SITE.support'));
    }

    public function testTenantSlugsAreNotReserved(): void
    {
        self::assertFalse(SystemReservedPermissions::isReserved('admin.access'));
        self::assertFalse(SystemReservedPermissions::isReserved('admin.organization'));
        self::assertFalse(SystemReservedPermissions::isReserved('admin.members.manage'));
        self::assertFalse(SystemReservedPermissions::isReserved('forum.moderate'));
        self::assertFalse(SystemReservedPermissions::isReserved('training.manage'));
        self::assertFalse(SystemReservedPermissions::isReserved(''));
    }

    public function testFilterRemovesOnlyReservedSlugs(): void
    {
        $filtered = SystemReservedPermissions::filter([
            'documents.view',
            'admin.system',
            'admin.access',
            'site.support',
            '*',
            'forum.view',
        ]);

        self::assertSame(['documents.view', 'admin.access', 'forum.view'], $filtered);
    }

    public function testReservedFromReportsWhatWasRefused(): void
    {
        self::assertSame(
            ['admin.system', 'site.support'],
            SystemReservedPermissions::reservedFrom(['documents.view', 'admin.system', 'site.support', 'admin.system'])
        );
    }

    public function testFilterMapKeysDropsReservedUnitScopedPermissions(): void
    {
        $map = ['documents.view' => [3], 'admin.system' => [3], 'site.support' => [7]];

        self::assertSame(['documents.view' => [3]], SystemReservedPermissions::filterMapKeys($map));
    }

    /** Le catalogue attribuable dans une communauté ne contient aucun slug plateforme. */
    public function testTenantPermissionCatalogHoldsNoReservedSlug(): void
    {
        $reserved = SystemReservedPermissions::reservedFrom(TenantPermissionCatalog::allSlugs());

        self::assertSame([], $reserved, 'Slugs plateforme présents dans le catalogue tenant : ' . implode(', ', $reserved));
    }

    /** La matrice des rôles (écran « Rôles & permissions ») n’accorde aucun slug plateforme. */
    public function testRolePermissionMatrixGrantsNoReservedSlug(): void
    {
        $granted = [];
        foreach (RolePermissionMatrixCatalog::moduleKeys() as $moduleKey) {
            foreach (RolePermissionMatrixCatalog::accessLevelKeys() as $level) {
                $granted = array_merge(
                    $granted,
                    RolePermissionMatrixCatalog::permissionSlugsForModuleLevel($moduleKey, $level)
                );
            }
        }
        $granted = array_merge($granted, RolePermissionMatrixCatalog::transversalPermissionSlugs(true, true));

        $reserved = SystemReservedPermissions::reservedFrom($granted);
        self::assertSame([], $reserved, 'Slugs plateforme accordés par la matrice : ' . implode(', ', $reserved));
    }

    /**
     * Garde-fou sur la raison d’être de l’invariant : `admin.system` est un laissez-passer
     * universel, et `admin.access` ne doit pas l’être.
     */
    public function testAdminSystemIsUniversalWhileTenantAdminIsNot(): void
    {
        self::assertTrue(PermissionImplication::isGranted(['admin.system'], 'site.tenants.manage'));
        self::assertFalse(PermissionImplication::isGranted(['admin.access'], 'admin.system'));
        self::assertFalse(PermissionImplication::isGranted(['admin.organization'], 'admin.system'));
        self::assertFalse(PermissionImplication::isGranted(['admin.access'], 'site.support'));
    }

    public function testOrganizationAdminImpliesDashboardPinsWithoutPlatformRights(): void
    {
        self::assertTrue(PermissionImplication::isGranted(['admin.organization'], 'dashboard.pins.manage'));
        self::assertTrue(PermissionImplication::isGranted(['admin.access'], 'dashboard.pins.manage'));
        self::assertFalse(PermissionImplication::isGranted(['admin.organization'], 'admin.system'));
        self::assertFalse(PermissionImplication::isGranted(['admin.access'], 'admin.system'));
        self::assertFalse(PermissionImplication::isGranted(['admin.organization'], 'site.support'));
        self::assertFalse(PermissionImplication::isGranted(['dashboard.pins.manage'], 'admin.system'));
        self::assertFalse(PermissionImplication::isGranted(['site.support'], 'dashboard.pins.manage'));
    }
}
