<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_sse_analytical (SSE — appréciation, lacunes, décisions) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$atakSseAnalyticalMigrate = require $root . '/bootstrap/atak_sse_analytical_migration.php';
try {
    echo "Migration atak_sse_analytical (SSE — appréciation, lacunes, décisions)…\n";
    $atakSseAnalyticalMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_sse_analytical : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_sse_analytical', $e);
}
$migrationEnsurePdo();
