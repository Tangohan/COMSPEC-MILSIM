<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class UnitSheetsEnrichmentAssetTest extends TestCase
{
    public function testIdentityMigrationAndUnitSheetAreWired(): void
    {
        $root = dirname(__DIR__, 2);
        $migration = (string) file_get_contents($root . '/bootstrap/unit_identity_enrichment_migration.php');
        $run = (string) file_get_contents($root . '/run-migrations.php');
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $navPayload = (string) file_get_contents($root . '/app/Support/OrbatRosterPayload.php');
        $billetSvc = (string) file_get_contents($root . '/app/Services/Organization/OrbatBilletService.php');
        $status = (string) file_get_contents($root . '/app/Support/UnitAdminStatus.php');
        $catalog = (string) file_get_contents($root . '/app/Services/ConfigurationUpdate/ConfigurationUpdateCatalog.php');
        $sheet = (string) file_get_contents($root . '/views/admin/organization/unit_sheet.php');
        $canvas = (string) file_get_contents($root . '/views/partials/orbat/orbat_canvas.php');
        $unite = (string) file_get_contents($root . '/views/admin/member_situation/unite.php');
        $dispatch = (string) file_get_contents($root . '/app/Support/DevDispatchCatalog.php');

        self::assertStringContainsString('function run_unit_identity_enrichment_migration', $migration);
        self::assertStringContainsString('unit_type_definitions', $migration);
        self::assertStringContainsString('unit_activity_log', $migration);
        self::assertStringContainsString('post_qualification_requirements', $migration);
        self::assertStringContainsString('run_unit_identity_enrichment_migration', $run);

        self::assertStringContainsString('UnitSheetController', $routes);
        self::assertStringContainsString("/back-office/organisation/unites/{id}", $routes);

        self::assertStringContainsString("'commander'", $navPayload);
        self::assertStringContainsString("'deputy'", $navPayload);
        self::assertStringContainsString('keyPostsHealth', $navPayload);
        self::assertStringContainsString('motto', $navPayload);

        self::assertStringContainsString('function ensureKeyPosts', $billetSvc);
        self::assertStringContainsString('function syncCommanderCache', $billetSvc);
        self::assertStringContainsString('function applyUnitTypeTemplate', $billetSvc);

        self::assertStringContainsString("RESERVE = 'reserve'", $status);
        self::assertStringContainsString('UNIT_TYPE_DEFINITIONS_V1', $catalog);

        self::assertStringContainsString('Journal d’activité', $sheet);
        self::assertStringContainsString('Postes (TO&amp;E)', $sheet);
        self::assertStringContainsString('Documents', $sheet);

        self::assertStringContainsString('is-health-critical', $canvas);
        self::assertStringContainsString('orbat-ed-motto', $canvas);
        self::assertStringContainsString('full-sheet', $canvas);
        self::assertStringContainsString('detail-deputy', $canvas);

        self::assertStringContainsString('unitMotto', $unite);
        self::assertStringContainsString('bo-unit-hero__motto', $unite);

        self::assertStringContainsString('$pr(732', $dispatch);
        self::assertFileExists($root . '/app/Repositories/UnitTypeDefinitionRepository.php');
        self::assertFileExists($root . '/app/Repositories/UnitActivityRepository.php');
        self::assertFileExists($root . '/app/Controllers/Admin/Organization/UnitSheetController.php');
    }
}
