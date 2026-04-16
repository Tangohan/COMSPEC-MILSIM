<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Rbac\MilitaryOperationalRoleCatalog;
use App\Services\Rbac\MilitaryRoleCatalogSyncService;
use PHPUnit\Framework\TestCase;

final class MilitaryOperationalRoleCatalogTest extends TestCase
{
    public function testSlugsAreUnique(): void
    {
        $slugs = [];
        foreach (MilitaryOperationalRoleCatalog::entries() as $e) {
            $slug = $e['slug'];
            self::assertArrayNotHasKey($slug, $slugs, 'Slug dupliqué : ' . $slug);
            $slugs[$slug] = true;
        }
    }

    public function testRequiredFieldsAreNonEmpty(): void
    {
        foreach (MilitaryOperationalRoleCatalog::entries() as $e) {
            self::assertNotSame('', trim((string) ($e['slug'] ?? '')), 'slug vide');
            self::assertNotSame('', trim((string) ($e['name'] ?? '')), 'name vide pour ' . ($e['slug'] ?? ''));
            self::assertNotSame('', trim((string) ($e['label_en'] ?? '')), 'label_en vide pour ' . ($e['slug'] ?? ''));
            self::assertNotSame('', trim((string) ($e['category'] ?? '')), 'category vide pour ' . ($e['slug'] ?? ''));
            self::assertNotSame('', trim((string) ($e['subcategory'] ?? '')), 'subcategory vide pour ' . ($e['slug'] ?? ''));
            self::assertNotSame('', trim((string) ($e['description'] ?? '')), 'description vide pour ' . ($e['slug'] ?? ''));
        }
    }

    public function testSemanticTierIsKnown(): void
    {
        $allowed = ['authority', 'function', 'specialty', 'status', 'support', 'liaison'];
        foreach (MilitaryOperationalRoleCatalog::entries() as $e) {
            self::assertContains($e['semantic_tier'], $allowed, $e['slug']);
        }
    }

    public function testPermissionBaselineIsKnown(): void
    {
        $allowed = ['member', 'officer', 'instructor', 'medic', 'logistics', 'hr', 'rto', 'probation'];
        foreach (MilitaryOperationalRoleCatalog::entries() as $e) {
            self::assertContains($e['permission_baseline'], $allowed, $e['slug']);
        }
    }

    public function testEachRootCategoryHasVisualIcon(): void
    {
        $visualsPath = dirname(__DIR__, 2) . '/config/role_catalog_visuals.php';
        self::assertFileExists($visualsPath);
        /** @var array{category_icons?: array<string, string>} $visuals */
        $visuals = require $visualsPath;
        $icons = $visuals['category_icons'] ?? [];
        $seen = [];
        foreach (MilitaryOperationalRoleCatalog::entries() as $e) {
            $cat = (string) $e['category'];
            if (isset($seen[$cat])) {
                continue;
            }
            $seen[$cat] = true;
            $key = MilitaryRoleCatalogSyncService::categoryKeyFromLabel($cat);
            self::assertArrayHasKey(
                $key,
                $icons,
                'Icône manquante pour la racine « ' . $cat . ' » (clé attendue : ' . $key . ')'
            );
            self::assertNotSame('', trim($icons[$key]), 'Icône vide pour ' . $key);
        }
    }

    public function testCatalogSlugSetMatchesEntries(): void
    {
        $set = MilitaryOperationalRoleCatalog::catalogSlugSet();
        $entries = MilitaryOperationalRoleCatalog::entries();
        self::assertCount(count($entries), $set);
        foreach ($entries as $e) {
            self::assertArrayHasKey($e['slug'], $set);
        }
    }

    /** Emplois de carrière : MOS / AOC de référence renseignés (hors statuts d’affichage et emplois sans équivalent MOS). */
    public function testCareerRolesHaveOfficialMosFields(): void
    {
        $withoutMos = ['sport_high_level_athlete'];
        foreach (MilitaryOperationalRoleCatalog::entries() as $e) {
            self::assertArrayHasKey('mos_code', $e);
            self::assertArrayHasKey('mos_specialty_title', $e);
            if ((int) ($e['is_visual_only'] ?? 0) === 1) {
                continue;
            }
            if (in_array($e['slug'], $withoutMos, true)) {
                continue;
            }
            self::assertNotNull($e['mos_code'], 'MOS manquant pour ' . $e['slug']);
            self::assertNotSame('', trim((string) $e['mos_code']), $e['slug']);
            self::assertNotNull($e['mos_specialty_title'], 'Intitulé MOS manquant pour ' . $e['slug']);
            self::assertNotSame('', trim((string) $e['mos_specialty_title']), $e['slug']);
        }
    }
}
