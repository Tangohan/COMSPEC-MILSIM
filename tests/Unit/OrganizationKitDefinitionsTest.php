<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\OrganizationCatalog\OrganizationKitDefinitions;
use PHPUnit\Framework\TestCase;

final class OrganizationKitDefinitionsTest extends TestCase
{
    public function testOfficialKitsAreTenantAgnosticAndComplete(): void
    {
        $kits = OrganizationKitDefinitions::officialKits();
        self::assertCount(4, $kits);
        self::assertSame(
            [
                OrganizationKitDefinitions::FRENCH_ARMY,
                OrganizationKitDefinitions::US_SOF,
                OrganizationKitDefinitions::INFANTRY_LIGHT,
                OrganizationKitDefinitions::GAMING_COMMUNITY,
            ],
            OrganizationKitDefinitions::officialCodes()
        );

        foreach ($kits as $kit) {
            self::assertArrayNotHasKey('tenant_id', $kit);
            $encoded = json_encode($kit);
            self::assertIsString($encoded);
            self::assertStringNotContainsString('"tenant_id"', $encoded);
            self::assertNotSame('', trim((string) ($kit['title'] ?? '')));
            self::assertNotSame('', trim((string) ($kit['summary'] ?? '')));
            self::assertNotEmpty($kit['units'] ?? []);
            self::assertNotEmpty($kit['job_roles'] ?? []);
            self::assertIsArray($kit['roles'] ?? null);
            self::assertNotSame('', (string) ($kit['grade_system_code'] ?? ''));
            $volume = OrganizationKitDefinitions::volumeLabel($kit);
            self::assertStringContainsString('unités', $volume);
            self::assertStringContainsString('fonctions', $volume);
            self::assertStringNotContainsString('0 rôle', $volume);
        }
    }

    public function testFrenchArmyIsAJobCatalogWithFrenchGrades(): void
    {
        $kit = OrganizationKitDefinitions::frenchArmy();
        self::assertSame(OrganizationKitDefinitions::FRENCH_ARMY, $kit['code']);
        self::assertSame('FR_CLASSIC', $kit['grade_system_code']);
        self::assertSame([], $kit['roles']);
        self::assertGreaterThanOrEqual(30, count($kit['job_roles']));
        $names = array_column($kit['job_roles'], 'name');
        self::assertContains('Chef de section', $names);
        self::assertContains('Voltigeur', $names);
        self::assertContains('Opérateur commando', $names);
        self::assertContains('Nageur de combat', $names);
        $slugs = array_column($kit['job_roles'], 'slug');
        foreach ($slugs as $slug) {
            self::assertStringStartsWith('armeefr-', (string) $slug);
        }
        $volume = OrganizationKitDefinitions::volumeLabel($kit);
        self::assertStringContainsString('système de grades', $volume);
        self::assertStringNotContainsString('rôle', $volume);
    }

    public function testUsSofIsAJobCatalogWithAmericanGrades(): void
    {
        $kit = OrganizationKitDefinitions::usSof();
        self::assertSame(OrganizationKitDefinitions::US_SOF, $kit['code']);
        self::assertSame('US_CLASSIC', $kit['grade_system_code']);
        self::assertSame([], $kit['roles']);
        self::assertGreaterThanOrEqual(20, count($kit['job_roles']));
        $names = array_column($kit['job_roles'], 'name');
        self::assertContains('Commandant de détachement', $names);
        self::assertContains('Sergent médical', $names);
        self::assertContains('Fusilier Ranger', $names);
        self::assertContains('Contrôleur de combat', $names);
        self::assertContains('Opérateur d’action navale', $names);
        $corpus = strtolower(json_encode($kit, JSON_UNESCAPED_UNICODE) ?: '');
        self::assertStringNotContainsString('cag', $corpus);
        self::assertStringNotContainsString('non public', $corpus);
        $slugs = array_column($kit['job_roles'], 'slug');
        foreach ($slugs as $slug) {
            self::assertStringStartsWith('ussof-', (string) $slug);
        }
        $volume = OrganizationKitDefinitions::volumeLabel($kit);
        self::assertStringNotContainsString('rôle', $volume);
    }

    public function testJobDoctrineCodesAndSlugsStayUnique(): void
    {
        self::assertSame(
            [OrganizationKitDefinitions::FRENCH_ARMY, OrganizationKitDefinitions::US_SOF],
            OrganizationKitDefinitions::jobDoctrineCodes()
        );

        $seen = [];
        foreach (OrganizationKitDefinitions::officialKits() as $kit) {
            foreach (['units', 'job_role_categories', 'job_roles'] as $group) {
                foreach (is_array($kit[$group] ?? null) ? $kit[$group] : [] as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $slug = trim((string) ($row['slug'] ?? ''));
                    if ($slug === '') {
                        continue;
                    }
                    $key = $group . ':' . $slug;
                    self::assertArrayNotHasKey($key, $seen, $key);
                    $seen[$key] = true;
                }
            }
        }
    }
}
