<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Authorization\TenantPermissionCatalog;
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
