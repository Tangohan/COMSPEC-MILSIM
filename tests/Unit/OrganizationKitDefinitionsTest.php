<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\OrganizationCatalog\OrganizationKitDefinitions;
use PHPUnit\Framework\TestCase;

final class OrganizationKitDefinitionsTest extends TestCase
{
    public function testOfficialKitsAreTenantAgnosticAndComplete(): void
    {
        $kits = OrganizationKitDefinitions::genericKits();
        self::assertCount(9, $kits);
        self::assertSame(
            [
                OrganizationKitDefinitions::FRENCH_ARMY,
                OrganizationKitDefinitions::US_SOF,
                OrganizationKitDefinitions::INFANTRY_LIGHT,
                OrganizationKitDefinitions::US_ARMY_INFANTRY,
                OrganizationKitDefinitions::SOF_TASK_FORCE,
                OrganizationKitDefinitions::FRENCH_ARMOR,
                OrganizationKitDefinitions::AIRMOBILE,
                OrganizationKitDefinitions::PRIVATE_MILITARY,
                OrganizationKitDefinitions::GAMING_COMMUNITY,
            ],
            OrganizationKitDefinitions::genericCodes()
        );
        // Identifiants existants inchangés, en tête ; les modèles d’unités réelles suivent.
        self::assertSame(array_column($kits, 'code'), OrganizationKitDefinitions::genericCodes());
        self::assertSame(
            array_merge(OrganizationKitDefinitions::genericCodes(), \App\Services\OrganizationCatalog\RealUnitKitDefinitions::codes()),
            OrganizationKitDefinitions::officialCodes()
        );
        self::assertSame(OrganizationKitDefinitions::officialCodes(), array_column(OrganizationKitDefinitions::officialKits(), 'code'));

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

    public function testKitUnitTreesAreConsistent(): void
    {
        foreach (OrganizationKitDefinitions::officialKits() as $kit) {
            $keys = array_column($kit['units'], 'key');
            self::assertSame(count($keys), count(array_unique($keys)), (string) $kit['code']);
            $categories = array_column($kit['job_role_categories'], 'key');
            foreach ($kit['units'] as $unit) {
                $parent = $unit['parent_key'] ?? null;
                if ($parent !== null) {
                    self::assertContains($parent, $keys, (string) $kit['code'] . ':' . $unit['key']);
                }
                self::assertMatchesRegularExpression('/^[a-z0-9]([a-z0-9-]{0,48}[a-z0-9])?$/', (string) $unit['slug']);
            }
            foreach ($kit['job_roles'] as $job) {
                self::assertContains($job['category_key'], $categories, (string) $job['slug']);
            }
            // Modèles allemands : aucun barème de grades allemand, le système de la communauté reste inchangé.
            // Modèles malaisiens : grades malaisiens (armée de terre, marine, armée de l’air).
            $grades = OrganizationKitDefinitions::isRealUnits($kit) ? array_merge(\App\Support\OfficialGradeSystems::CODES, ['']) : ['FR_CLASSIC', 'US_CLASSIC'];
            self::assertContains($kit['grade_system_code'], $grades);
        }
    }

    public function testJobDoctrineCodesAndSlugsStayUnique(): void
    {
        self::assertSame(
            [OrganizationKitDefinitions::FRENCH_ARMY, OrganizationKitDefinitions::US_SOF],
            OrganizationKitDefinitions::jobDoctrineCodes()
        );

        // Une adresse courte n’appartient qu’à un modèle, sauf une unité réelle reprise par plusieurs modèles
        // (USSOCOM et USASOC…) : même nom et même code partout, pour compléter la même arborescence.
        $seen = [];
        foreach (OrganizationKitDefinitions::officialKits() as $kit) {
            $inKit = [];
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
                    self::assertArrayNotHasKey($key, $inKit, $kit['code'] . ' ' . $key);
                    $inKit[$key] = true;
                    $same = [(string) ($row['name'] ?? ''), (string) ($row['code'] ?? '')];
                    if (isset($seen[$key])) {
                        self::assertTrue(OrganizationKitDefinitions::isRealUnits($kit) && $seen[$key][1], $key);
                        self::assertSame($seen[$key][0], $same, $key);
                    }
                    $seen[$key] = [$same, OrganizationKitDefinitions::isRealUnits($kit)];
                }
            }
        }
    }
}
