<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Tactical\AtlasMapCatalog;
use App\Support\AtakRemoteTileGuard;
use PHPUnit\Framework\TestCase;

/** Les 289 cartes de l'atlas plan-ops deviennent des lignes atak_maps utilisables par le site et par la résolution du monde du jeu. */
final class AtlasMapCatalogTest extends TestCase
{
    public function testEveryMapOfTheSnapshotBecomesAUsableRow(): void
    {
        $rows = AtlasMapCatalog::rows();
        self::assertTrue(count($rows) >= 280, 'Le catalogue doit garder ses cartes : ' . count($rows));
        $slugs = [];
        foreach ($rows as $r) {
            self::assertTrue(!isset($slugs[$r['slug']]), 'Slug en double : ' . $r['slug']);
            $slugs[$r['slug']] = true;
            self::assertMatchesRegularExpression('/^[a-z0-9_-]{1,50}$/', $r['slug']);
            self::assertMatchesRegularExpression('/^[a-z0-9_.-]{1,50}$/', $r['world_name']);
            self::assertTrue(strlen($r['tile_pattern']) < 500);
            self::assertTrue(str_starts_with($r['tile_pattern'], 'https://atlas.plan-ops.fr/data/1/maps/'));
            $c = $r['config'];
            self::assertTrue($c['worldSize'] > 0 && $c['tileSize'] > 0 && $c['maxZoom'] >= $c['minZoom']);
            self::assertTrue($c['crs']['factorx'] > 0 && $c['crs']['factory'] > 0 && $c['crs']['tileWidth'] > 0);
            self::assertSame('atlas', $c['source']);
            // Le relais de tuiles du site accepte les tuiles de chaque carte (sinon la carte restait grise).
            $tile = str_replace(['{z}', '{x}', '{y}'], ['0', '0', '0'], $r['tile_pattern']);
            self::assertSame($tile, AtakRemoteTileGuard::normalize($tile), $r['slug'] . ' : tuile refusée par le garde-fou');
        }
    }

    public function testKnownTheatresAreThere(): void
    {
        $by = [];
        foreach (AtlasMapCatalog::rows() as $r) {
            $by[$r['slug']] = $r;
        }
        foreach (['altis', 'stratis', 'malden', 'tanoa', 'enoch', 'sara', 'takistan'] as $slug) {
            self::assertTrue(isset($by[$slug]), $slug . ' absente du catalogue');
        }
        self::assertSame(30720, $by['altis']['config']['worldSize']);
        self::assertSame('Altis', $by['altis']['label']);
    }

    public function testBrokenEntriesAreSkippedNotFatal(): void
    {
        $json = json_encode(['tile_base' => 'https://atlas.plan-ops.fr/data/1', 'maps' => [
            ['slug' => 'ok', 'world' => 'ok', 'title' => 'Ok', 'tile_pattern' => '/maps/1/1/{z}/{x}/{y}.png', 'crs' => [0.01, 0.01, 200], 'world_size' => 1000, 'tile_size' => 200, 'center' => [500, 500], 'max_zoom' => 3],
            ['slug' => 'ok', 'world' => 'dup', 'tile_pattern' => '/maps/1/1/{z}/{x}/{y}.png', 'crs' => [0.01, 0.01, 200], 'world_size' => 1000, 'tile_size' => 200],
            ['slug' => 'noCrs', 'world' => 'x', 'tile_pattern' => '/maps/1/1/{z}/{x}/{y}.png', 'world_size' => 1000, 'tile_size' => 200],
            ['slug' => 'evil', 'world' => 'x', 'tile_pattern' => 'https://evil.example/{z}/{x}/{y}.png', 'crs' => [0.01, 0.01, 200], 'world_size' => 1000, 'tile_size' => 200],
            'pas une carte',
        ]]);
        $rows = AtlasMapCatalog::rows((string) $json);
        self::assertCount(1, $rows);
        self::assertSame('ok', $rows[0]['slug']);
        self::assertSame([], AtlasMapCatalog::rows('{pas du json'));
    }

    public function testImportNeverTouchesAHandSetMapAndIsRegistered(): void
    {
        $root = dirname(__DIR__, 2);
        $src = str_replace("\r\n", "\n", (string) file_get_contents($root . '/app/Services/Tactical/AtlasMapCatalog.php'));
        self::assertStringContainsString("(\$current['source'] ?? '') !== 'atlas'", $src);
        $reg = (string) file_get_contents($root . '/database/steps/registry.php');
        self::assertStringContainsString("'file' => '4490_atak_maps_atlas.php'", $reg);
        self::assertTrue(is_file($root . '/database/steps/4490_atak_maps_atlas.php'));
    }
}
