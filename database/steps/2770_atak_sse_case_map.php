<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_sse_case_map (SSE — carte permanente des dossiers) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$atakSseCaseMapMigrate = require $root . '/bootstrap/atak_sse_case_map_migration.php';
try {
    echo "Migration atak_sse_case_map (SSE — carte permanente des dossiers)…\n";
    $atakSseCaseMapMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_sse_case_map : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_sse_case_map', $e);
}
$migrationEnsurePdo();
