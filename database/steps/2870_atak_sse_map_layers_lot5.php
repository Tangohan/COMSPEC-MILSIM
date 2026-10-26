<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_sse_map_layers_lot5 (SSE — LOT 5 Calques ATAK) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$atakSseMapLayersLot5Migrate = require $root . '/bootstrap/atak_sse_map_layers_lot5_migration.php';
try {
    echo "Migration atak_sse_map_layers_lot5 (SSE — LOT 5 Calques ATAK)…\n";
    $atakSseMapLayersLot5Migrate($pdo, $sseCliLog);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_sse_map_layers_lot5 : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_sse_map_layers_lot5', $e);
}
$migrationEnsurePdo();
