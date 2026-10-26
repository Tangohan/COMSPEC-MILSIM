<?php

declare(strict_types=1);

/**
 * Étape « Cartes ATAK (théâtres Arma et CDN des tuiles) » (Référentiels).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Seed / upsert atak_maps (théâtres Arma via CDN jetelain/Arma3Map)
$atakTileCdn = rtrim((string) (getenv('ATAK_MAP_TILES_CDN') ?: 'https://jetelain.github.io/Arma3Map'), '/');
$atakMapsSeed = [
    [
        'slug' => 'altis',
        'label' => 'Altis',
        'world_name' => 'altis',
        'display_order' => 0,
        'factorx' => 0.006839,
        'factory' => 0.006836,
        'tileSize' => 212,
        'worldSize' => 30720,
        'center' => [15000, 15000],
        'defaultZoom' => 3,
        'maxZoom' => 6,
        'attribution' => '&copy; Bohemia Interactive',
        'aerial' => [
            'tilePattern' => 'https://atlas.plan-ops.fr/data/1/maps/3/295/{z}/{x}/{y}.webp',
            'factorX' => 0.012375,
            'factorY' => 0.012375,
            'tileSize' => 381,
            'minZoom' => 0,
            'maxZoom' => 7,
            'attribution' => '&copy; Bohemia Interactive',
        ],
    ],
    [
        'slug' => 'stratis',
        'label' => 'Stratis',
        'world_name' => 'stratis',
        'display_order' => 1,
        'factorx' => 0.027475,
        'factory' => 0.027475,
        'tileSize' => 226,
        'worldSize' => 8192,
        'center' => [4100, 4100],
        'defaultZoom' => 2,
        'maxZoom' => 4,
        'attribution' => '&copy; Bohemia Interactive',
    ],
    [
        'slug' => 'malden',
        'label' => 'Malden',
        'world_name' => 'malden',
        'display_order' => 2,
        'factorx' => 0.01448,
        'factory' => 0.01448,
        'tileSize' => 186,
        'worldSize' => 12800,
        'center' => [7000, 7000],
        'defaultZoom' => 2,
        'maxZoom' => 5,
        'attribution' => '&copy; Bohemia Interactive',
    ],
    [
        'slug' => 'tanoa',
        'label' => 'Tanoa',
        'world_name' => 'tanoa',
        'display_order' => 3,
        'factorx' => 0.01385,
        'factory' => 0.01385,
        'tileSize' => 213,
        'worldSize' => 15360,
        'center' => [7000, 7000],
        'defaultZoom' => 2,
        'maxZoom' => 5,
        'attribution' => '&copy; Bohemia Interactive',
    ],
    [
        'slug' => 'enoch',
        'label' => 'Livonia',
        'world_name' => 'enoch',
        'display_order' => 4,
        'factorx' => 0.02735,
        'factory' => 0.02735,
        'tileSize' => 356,
        'worldSize' => 12800,
        'center' => [7100, 7100],
        'defaultZoom' => 2,
        'maxZoom' => 4,
        'attribution' => '&copy; Bohemia Interactive',
    ],
    [
        'slug' => 'chernarus',
        'label' => 'Chernarus',
        'world_name' => 'chernarus',
        'display_order' => 5,
        'factorx' => 0.01575,
        'factory' => 0.01575,
        'tileSize' => 242,
        'worldSize' => 15360,
        'center' => [7680, 7680],
        'defaultZoom' => 2,
        'maxZoom' => 5,
        'attribution' => '&copy; Bohemia Interactive, CUP Team',
    ],
    [
        'slug' => 'takistan',
        'label' => 'Takistan',
        'world_name' => 'takistan',
        'display_order' => 6,
        'factorx' => 0.01575,
        'factory' => 0.01575,
        'tileSize' => 202,
        'worldSize' => 12800,
        'center' => [6400, 6400],
        'defaultZoom' => 2,
        'maxZoom' => 5,
        'attribution' => '&copy; Bohemia Interactive, CUP Team',
    ],
    [
        // GameMapStorage (successeur Arma3Map) : pas de pyramide sur GitHub Pages.
        // JS officiel : https://atlas.plan-ops.fr/data/1/maps/sze_kimmirut.js
        'slug' => 'sze_kimmirut',
        'label' => 'Kimmirut',
        'world_name' => 'sze_kimmirut',
        'display_order' => 7,
        'factorx' => 0.01575,
        'factory' => 0.01575,
        'tileSize' => 323,
        'worldSize' => 20480,
        'center' => [10240, 10240],
        'defaultZoom' => 2,
        'maxZoom' => 6,
        'attribution' => '&copy; Bohemia Interactive, szephi',
        'tile_cdn' => 'https://atlas.plan-ops.fr/data/1',
        'tile_path' => 'maps/107/107/{z}/{x}/{y}.png',
    ],
];
try {
    $chkMap = $pdo->prepare('SELECT id, tile_pattern FROM atak_maps WHERE ' . \App\Support\SqlText::equals($pdo, 'slug') . ' LIMIT 1');
    $insMap = $pdo->prepare('INSERT INTO atak_maps (slug, label, world_name, tile_pattern, config, display_order) VALUES (?, ?, ?, ?, ?, ?)');
    $updMap = $pdo->prepare('UPDATE atak_maps SET label = ?, world_name = ?, tile_pattern = ?, config = ?, display_order = ? WHERE ' . \App\Support\SqlText::equals($pdo, 'slug'));
    $seeded = 0;
    $updated = 0;
    foreach ($atakMapsSeed as $row) {
        $slug = (string) $row['slug'];
        $ws = (int) $row['worldSize'];
        $ts = (int) $row['tileSize'];
        $mapCdn = rtrim((string) ($row['tile_cdn'] ?? $atakTileCdn), '/');
        $mapPath = ltrim((string) ($row['tile_path'] ?? ('maps/' . $slug . '/{z}/{x}/{y}.png')), '/');
        $tilePattern = $mapCdn . '/' . $mapPath;
        $hasCustomTiles = isset($row['tile_cdn']) || isset($row['tile_path']);
        $configPayload = [
            'center' => $row['center'],
            'defaultZoom' => (int) $row['defaultZoom'],
            'minZoom' => 0,
            'maxZoom' => (int) $row['maxZoom'],
            'tileSize' => $ts,
            'worldSize' => $ws,
            'bounds' => [[0, 0], [$ws, $ws]],
            'crs' => [
                'factorx' => (float) $row['factorx'],
                'factory' => (float) $row['factory'],
                'tileWidth' => $ts,
            ],
            'attribution' => (string) $row['attribution'],
            'title' => (string) $row['label'],
        ];
        if (!empty($row['aerial']) && is_array($row['aerial'])) {
            $configPayload['aerial'] = $row['aerial'];
        }
        $configJson = json_encode($configPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $chkMap->execute([$slug]);
        $existing = $chkMap->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            $insMap->execute([
                $slug,
                (string) $row['label'],
                (string) $row['world_name'],
                $tilePattern,
                $configJson,
                (int) $row['display_order'],
            ]);
            $seeded++;
            continue;
        }
        $oldPattern = trim((string) ($existing['tile_pattern'] ?? ''));
        $forceCdn = $oldPattern === ''
            || str_contains($oldPattern, '/assets/maps/')
            || str_contains($oldPattern, 'assets/maps/')
            || str_contains($oldPattern, 'ressources/MapViewers/')
            || !str_contains($oldPattern, '/maps/' . $slug . '/');
        $nextPattern = ($forceCdn || $hasCustomTiles) ? $tilePattern : $oldPattern;
        $updMap->execute([
            (string) $row['label'],
            (string) $row['world_name'],
            $nextPattern,
            $configJson,
            (int) $row['display_order'],
            $slug,
        ]);
        $updated++;
    }
    if ($seeded > 0 || $updated > 0) {
        echo "atak_maps seed/upsert : +{$seeded} insert, {$updated} maj (CDN {$atakTileCdn}).\n";
    }
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_maps seed : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_maps seed', $e);
}
