<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\UnitAdminStatus;
use App\Support\VisibilityLevel;
use PHPUnit\Framework\TestCase;

/**
 * Garde-fous architecture postes ORBAT / grade / fonction / effectifs.
 */
final class OrbatBilletArchitectureTest extends TestCase
{
    public function testMigrationDefinesBilletEnrichmentAndCareerJournal(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/bootstrap/orbat_billets_management_migration.php');
        self::assertStringContainsString('org_callsign', $src);
        self::assertStringContainsString('occupancy_type', $src);
        self::assertStringContainsString('personnel_career_journal', $src);
        self::assertStringContainsString('orbat_structure_snapshots', $src);
        self::assertStringContainsString('personnel_admin_notes', $src);
        self::assertStringContainsString('personnel_admin_status_definitions', $src);
        self::assertStringContainsString('organization_movement_reasons', $src);
        self::assertStringContainsString('is_key_post', $src);
        self::assertStringContainsString('keeps_organic_billet', $src);
    }

    public function testRepositoryExposesManningAndOccupancyApi(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/OrbatBilletRepository.php');
        self::assertStringContainsString('function manningForUnit', $src);
        self::assertStringContainsString('function assignHolder', $src);
        self::assertStringContainsString('function activeHolders', $src);
        self::assertStringContainsString('occupancy_type', $src);
        self::assertStringContainsString('authorized_slots', $src);
    }

    public function testServiceSeparatesGradeFromFunctionAndSupportsActing(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Organization/OrbatBilletService.php');
        self::assertStringContainsString("'acting'", $src);
        self::assertStringContainsString('keeps_organic_billet', $src);
        self::assertStringContainsString('derivedCommand', $src);
        self::assertStringContainsString('detectAnomalies', $src);
        self::assertStringContainsString('missing_qualification', $src);
        self::assertStringContainsString('duplicate_primary', $src);
        self::assertStringContainsString('vacant_billet', $src);
    }

    public function testRosterPayloadAttachesBilletManning(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Support/OrbatRosterPayload.php');
        self::assertStringContainsString('attachBilletManning', $src);
        self::assertStringContainsString('billetManningLabel', $src);
        self::assertStringContainsString('strengthTheoretical', $src);
        self::assertStringContainsString('strengthVacant', $src);
    }

    public function testApiExposesBilletActions(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Api/OrbatApiController.php');
        self::assertStringContainsString('billet_create', $src);
        self::assertStringContainsString('billet_occupy', $src);
        self::assertStringContainsString('billet_vacate', $src);
        self::assertStringContainsString('OrbatBilletService', $src);
    }

    public function testAdminStatusRemainsIndependentFromVisibility(): void
    {
        self::assertSame(UnitAdminStatus::ACTIVE, UnitAdminStatus::normalize('actif'));
        self::assertSame(UnitAdminStatus::ARCHIVED, UnitAdminStatus::normalize('archived'));
        self::assertTrue(UnitAdminStatus::isAssignableForbidden(UnitAdminStatus::ARCHIVED));
        self::assertFalse(UnitAdminStatus::isAssignableForbidden(UnitAdminStatus::INACTIVE));
        self::assertSame(VisibilityLevel::HIDDEN, VisibilityLevel::normalize('hidden'));
        self::assertNotSame(UnitAdminStatus::ARCHIVED, VisibilityLevel::HIDDEN);
    }

    public function testCanvasShowsBilletManningLabelAndVacantPosts(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/views/partials/orbat/orbat_canvas.php');
        self::assertStringContainsString('billetManningLabel', $src);
        self::assertStringContainsString('strengthTheoretical', $src);
        self::assertStringContainsString('detail-billets', $src);
        self::assertStringContainsString('ORBAT théorique', $src);
        self::assertStringContainsString('seat_status', $src);
    }
}
