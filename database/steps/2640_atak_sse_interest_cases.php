<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_sse_interest_cases (SSE — dossiers d’intérêt) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$atakSseInterestMigrate = require $root . '/bootstrap/atak_sse_interest_cases_migration.php';
try {
    echo "Migration atak_sse_interest_cases (SSE — dossiers d’intérêt)…\n";
    $atakSseInterestMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_sse_interest_cases : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_sse_interest_cases', $e);
}
$migrationEnsurePdo();
