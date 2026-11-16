<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Authorization\TenantPermissionCatalog;
use App\Repositories\UnitRepository;
use App\Services\Personnel\JobRoleEnrichmentService;
use App\Services\Community\TenantSeedHelper;
use App\Support\Schema\SeedNameLengthAudit;
use PHPUnit\Framework\TestCase;

/**
 * Libellés semés à la création d'une communauté contre les longueurs de colonnes des migrations.
 * Origine : le droit atak.toc.phone (102 caractères) ne tenait pas dans permissions.name VARCHAR(100) ; en MySQL strict,
 * toute création de communauté échouait (SQLSTATE 22001, « Data too long for column 'name' ») et était annulée.
 */
final class SeedNameLengthAuditTest extends TestCase
{
    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testEverySeededLabelFitsItsNarrowestColumn(): void
    {
        self::assertSame([], SeedNameLengthAudit::violations(self::root()));
    }

    public function testLimitsAreReadFromTheMigrations(): void
    {
        $limits = SeedNameLengthAudit::columnLimits(self::root());
        self::assertSame(100, $limits['permissions.name'] ?? null);
        self::assertSame(100, $limits['roles.name'] ?? null);
        self::assertSame(100, $limits['personnel_admin_panels.name'] ?? null);
        self::assertSame(120, $limits['personnel_job_roles.name'] ?? null);
        self::assertSame(40, $limits['personnel_job_role_tags.name'] ?? null);
        self::assertSame(40, $limits['sse_attach_tags.name'] ?? null);
        self::assertSame(80, $limits['sse_attach_tags.name_fr'] ?? null);
        self::assertSame(255, $limits['tenants.name'] ?? null);
        self::assertSame(255, $limits['units.name'] ?? null);
    }

    public function testAnOverlongValueIsReported(): void
    {
        $limits = SeedNameLengthAudit::columnLimits(self::root());
        $longest = 0;
        foreach (TenantPermissionCatalog::definitions() as $def) {
            $longest = max($longest, mb_strlen($def['name'], 'UTF-8'));
        }
        $limits['permissions.name'] = $longest - 1;
        $found = array_values(array_filter(
            SeedNameLengthAudit::violations(self::root(), $limits),
            static fn (string $line): bool => str_starts_with($line, 'permissions.name VARCHAR(' . ($longest - 1) . ') : ' . $longest . ' caractères dans TenantPermissionCatalog')
        ));
        self::assertNotSame([], $found);
        // La borne de troncature du code (100) devient elle aussi trop large pour la colonne.
        self::assertContains(
            'permissions.name VARCHAR(' . ($longest - 1) . ') : borne 100 trop large (TenantSeedHelper::PERMISSION_NAME_MAX (troncature)).',
            SeedNameLengthAudit::violations(self::root(), $limits)
        );
    }

    public function testEveryVarcharColumnIsReadNotOnlyName(): void
    {
        $limits = SeedNameLengthAudit::columnLimits(self::root());
        self::assertSame(100, $limits['units.slug'] ?? null);
        self::assertSame(20, $limits['units.code'] ?? null);
        self::assertSame(40, $limits['units.short_label'] ?? null);
        self::assertSame(80, $limits['personnel_job_roles.slug'] ?? null);
        self::assertSame(500, $limits['personnel_job_roles.description'] ?? null);
        self::assertSame(40, $limits['grade_definitions.short_label'] ?? null);
        self::assertSame(180, $limits['organization_catalog_items.title'] ?? null);
    }

    public function testStructureSourcesCoverUnitsJobsGradesAndCourses(): void
    {
        $columns = [];
        foreach (SeedNameLengthAudit::structureSources() as $source) {
            $columns[$source['column']] = count($source['values']);
        }
        foreach (['units.slug', 'units.code', 'personnel_job_roles.slug', 'grade_definitions.label', 'training_lessons.title', 'permissions.slug', 'sse_attach_tags.code'] as $column) {
            self::assertGreaterThan(0, $columns[$column] ?? 0, $column);
        }
        $courses = SeedNameLengthAudit::courseSeedLiterals(self::root());
        self::assertContains('parcours-portail', $courses['slug']);
    }

    public function testAnOverlongUnitCodeOrDerivedSlugIsReported(): void
    {
        $limits = SeedNameLengthAudit::columnLimits(self::root());
        $limits['units.code'] = 5;
        $limits['units.slug'] = 90;
        $lines = SeedNameLengthAudit::violations(self::root(), $limits);
        self::assertNotSame([], array_filter($lines, static fn (string $l): bool => str_starts_with($l, 'units.code VARCHAR(5) : ')));
        self::assertContains('units.slug VARCHAR(90) : borne 97 trop large (UnitRepository::SLUG_BASE_MAX + suffixe « -abcdef »).', $lines);
    }

    public function testDerivedUnitSlugAndFieldLengths(): void
    {
        self::assertSame(UnitRepository::SLUG_BASE_MAX, strlen(UnitRepository::slugFromName(str_repeat('Alpha ', 60))));
        self::assertSame('unite', UnitRepository::slugFromName('Взвод'));
        self::assertNull(UnitRepository::fieldLengthError(['name' => str_repeat('é', 255), 'code' => str_repeat('C', 20)]));
        self::assertSame('Le code de l’unité ne peut pas dépasser 20 caractères.', UnitRepository::fieldLengthError(['code' => str_repeat('C', 21)]));
        self::assertSame('Le nom de l’unité est requis.', UnitRepository::fieldLengthError(['name' => null]));
        self::assertSame(JobRoleEnrichmentService::tagKey('Médical'), JobRoleEnrichmentService::tagKey('MEDICAL'));
        self::assertNotSame(JobRoleEnrichmentService::tagKey('Отряд'), JobRoleEnrichmentService::tagKey('Взвод'));
    }

    public function testMissingColumnIsReportedRatherThanSilentlyAccepted(): void
    {
        $limits = SeedNameLengthAudit::columnLimits(self::root());
        unset($limits['roles.name']);
        $lines = SeedNameLengthAudit::violations(self::root(), $limits);
        self::assertNotSame([], array_filter($lines, static fn (string $l): bool => str_starts_with($l, 'roles.name : longueur introuvable')));
    }

    public function testPermissionNameIsCutToTheColumnLength(): void
    {
        $long = str_repeat('é', TenantSeedHelper::PERMISSION_NAME_MAX + 7);
        $cut = TenantSeedHelper::permissionName($long);
        self::assertSame(TenantSeedHelper::PERMISSION_NAME_MAX, mb_strlen($cut, 'UTF-8'));
        self::assertSame('Voir les documents', TenantSeedHelper::permissionName('  Voir les documents '));
    }

    public function testCliToolExitsZeroOnTheCurrentCode(): void
    {
        $out = [];
        $code = 1;
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(self::root() . '/tools/check-seed-name-lengths.php'), $out, $code);
        self::assertSame(0, $code, implode("\n", $out));
        self::assertStringContainsString('[OK]', implode("\n", $out));
    }
}
