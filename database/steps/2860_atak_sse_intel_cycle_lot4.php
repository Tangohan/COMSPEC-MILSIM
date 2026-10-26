<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_sse_intel_cycle_lot4 (SSE — LOT 4 Cycle renseignement) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$atakSseIntelCycleLot4Migrate = require $root . '/bootstrap/atak_sse_intel_cycle_lot4_migration.php';
try {
    echo "Migration atak_sse_intel_cycle_lot4 (SSE — LOT 4 Cycle renseignement)…\n";
    $atakSseIntelCycleLot4Migrate($pdo, $sseCliLog);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_sse_intel_cycle_lot4 : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_sse_intel_cycle_lot4', $e);
}
$migrationEnsurePdo();
