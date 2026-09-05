<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Rbac\PermissionScopeResolver;
use PHPUnit\Framework\TestCase;

final class PermissionScopeResolverTest extends TestCase
{
    public function testTenantWideRoleGrantsEveryPermissionScope(): void
    {
        foreach (['global', 'tenant', 'unit'] as $scope) {
            self::assertSame(
                ['flat' => true, 'unit_id' => null],
                PermissionScopeResolver::resolve($scope, null)
            );
        }
    }

    public function testUnitRoleOnlyGrantsUnitScopedPermissionsInsideItsUnit(): void
    {
        self::assertSame(
            ['flat' => false, 'unit_id' => 42],
            PermissionScopeResolver::resolve('unit', 42)
        );
        self::assertSame(
            ['flat' => false, 'unit_id' => null],
            PermissionScopeResolver::resolve('tenant', 42)
        );
        self::assertSame(
            ['flat' => false, 'unit_id' => null],
            PermissionScopeResolver::resolve('global', 42)
        );
    }
}
