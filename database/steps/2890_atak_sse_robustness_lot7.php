<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_sse_robustness_lot7 (SSE — LOT 7 Robustesse) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$atakSseRobustnessLot7Migrate = require $root . '/bootstrap/atak_sse_robustness_lot7_migration.php';
try {
    echo "Migration atak_sse_robustness_lot7 (SSE — LOT 7 Robustesse)…\n";
    $atakSseRobustnessLot7Migrate($pdo, $sseCliLog);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_sse_robustness_lot7 : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_sse_robustness_lot7', $e);
}
$migrationEnsurePdo();
