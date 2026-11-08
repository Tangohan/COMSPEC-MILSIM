<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Types d'entité personnalisés : câblage par lecture de fichier (création, modification, ORBAT, droits, migration).
 */
final class UnitStructTypesWiringTest extends TestCase
{
    private function src(string $path): string
    {
        $root = dirname(__DIR__, 2);

        return (string) file_get_contents($root . '/' . $path);
    }

    public function testOrbatEndpointsUseTenantTypes(): void
    {
        $api = $this->src('app/Controllers/Api/OrbatApiController.php');
        self::assertStringContainsString('UnitStructTypes::forTenant($tenantId)', $api);
        self::assertStringContainsString('UnitStructTypes::allowedIds($tenantId)', $api);
        self::assertStringNotContainsString("array_keys(config('units.types'", $api);
        self::assertStringContainsString('UnitStructTypes::forTenant', $this->src('views/partials/orbat/orbat_canvas.php'));
        self::assertStringContainsString('UnitStructTypes::allowedIds', $this->src('app/Controllers/Admin/Organization/GroupAdminController.php'));
    }

    public function testManagerPageIsGuardedAndTenantScoped(): void
    {
        $ctl = $this->src('app/Controllers/Admin/Organization/UnitStructTypeController.php');
        self::assertStringContainsString("'organization.orbat.manage'", $ctl);
        self::assertStringContainsString('Csrf::validate', $ctl);
        self::assertStringContainsString("Session::get('tenant_id')", $ctl);
        self::assertStringContainsString('countUnitsUsing', $ctl);
        $repo = $this->src('app/Repositories/UnitStructTypeRepository.php');
        self::assertStringContainsString('WHERE tenant_id = ? AND type = ?', $repo);
        self::assertStringContainsString('SqlText::equals', $repo);
        $routes = $this->src('routes/web.php');
        self::assertStringContainsString('/back-office/organisation/types-entite/enregistrer', $routes);
    }

    public function testMigrationIsRegisteredAndTranslated(): void
    {
        self::assertStringContainsString('4010_unit_struct_types.php', $this->src('database/steps/registry.php'));
        self::assertStringContainsString('tenant_unit_struct_types', $this->src('database/steps/4010_unit_struct_types.php'));
        $fr = require dirname(__DIR__, 2) . '/lang/fr/unit_types.php';
        $en = require dirname(__DIR__, 2) . '/lang/en/unit_types.php';
        self::assertSame(array_keys($fr), array_keys($en));
    }
}
