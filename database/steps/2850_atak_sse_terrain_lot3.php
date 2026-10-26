<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_sse_terrain_lot3 (SSE — LOT 3 Terrain) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$atakSseTerrainLot3Migrate = require $root . '/bootstrap/atak_sse_terrain_lot3_migration.php';
try {
    echo "Migration atak_sse_terrain_lot3 (SSE — LOT 3 Terrain)…\n";
    $atakSseTerrainLot3Migrate($pdo, $sseCliLog);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_sse_terrain_lot3 : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_sse_terrain_lot3', $e);
}
$migrationEnsurePdo();
