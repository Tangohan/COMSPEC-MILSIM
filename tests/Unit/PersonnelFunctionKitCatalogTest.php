<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Personnel\PersonnelFunctionKitCatalog;
use App\Services\Personnel\PersonnelFunctionKitService;
use App\Services\Rbac\MilitaryOperationalRoleCatalog;
use PHPUnit\Framework\TestCase;

final class PersonnelFunctionKitCatalogTest extends TestCase
{
    public function testKitIdsAreUniqueAndHuman(): void
    {
        $ids = [];
        foreach (PersonnelFunctionKitCatalog::all() as $kit) {
            $id = (string) ($kit['id'] ?? '');
            self::assertNotSame('', $id);
            self::assertArrayNotHasKey($id, $ids, 'Identifiant de kit dupliqué : ' . $id);
            $ids[$id] = true;
            self::assertNotSame('', trim((string) ($kit['label'] ?? '')));
            self::assertNotSame('', trim((string) ($kit['summary'] ?? '')));
            self::assertGreaterThanOrEqual(3, count($kit['key_slugs']));
            self::assertLessThanOrEqual(8, count($kit['key_slugs']));
        }
        self::assertGreaterThanOrEqual(8, count($ids));
        self::assertLessThanOrEqual(14, count($ids));
    }

    public function testKeySlugsExistInMilitaryCatalog(): void
    {
        $catalog = MilitaryOperationalRoleCatalog::catalogSlugSet();
        foreach (PersonnelFunctionKitCatalog::all() as $kit) {
            foreach ($kit['key_slugs'] as $slug) {
                self::assertArrayHasKey($slug, $catalog, $kit['id'] . ' ' . $slug);
            }
            foreach ($kit['extra_slugs'] as $slug) {
                self::assertArrayHasKey($slug, $catalog, $kit['id'] . ' extra ' . $slug);
            }
        }
    }

    public function testEmptyKitsMeanNoSlugUnion(): void
    {
        self::assertSame([], PersonnelFunctionKitCatalog::slugsForKitIds([]));
        self::assertSame([], PersonnelFunctionKitCatalog::keyFunctionsForKitIds([]));
    }

    public function testInfantryKitResolvesKnownCombatSlugs(): void
    {
        $slugs = PersonnelFunctionKitCatalog::slugsForKitIds(['infantry']);
        self::assertContains('infantry_rifleman', $slugs);
        self::assertContains('infantry_section_chief', $slugs);
        self::assertNotContains('medical_officer', $slugs);
    }

    public function testFilterKeepsAssignedCustomAndCatalogHits(): void
    {
        $allowed = ['infantry_rifleman' => true];
        $catalog = MilitaryOperationalRoleCatalog::catalogSlugSet();
        $filtered = PersonnelFunctionKitService::filterOptionsByAllowedSlugs(
            [
                ['id' => 1, 'slug' => 'infantry_rifleman', 'label' => 'Fusilier'],
                ['id' => 2, 'slug' => 'medical_officer', 'label' => 'Médecin militaire'],
                ['id' => 3, 'slug' => 'maison_custom', 'label' => 'Fonction maison'],
            ],
            $allowed,
            $catalog
        );
        $slugs = array_column($filtered, 'slug');
        self::assertContains('infantry_rifleman', $slugs);
        self::assertContains('maison_custom', $slugs);
        self::assertNotContains('medical_officer', $slugs);
    }
}
