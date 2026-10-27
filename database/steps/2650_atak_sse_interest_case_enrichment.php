<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_sse_interest_case_enrichment (SSE — DI description, ACL, journal, délais) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$atakSseInterestEnrichMigrate = require $root . '/bootstrap/atak_sse_interest_case_enrichment_migration.php';
try {
    echo "Migration atak_sse_interest_case_enrichment (SSE — DI description, ACL, journal, délais)…\n";
    $atakSseInterestEnrichMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_sse_interest_case_enrichment : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_sse_interest_case_enrichment', $e);
}
$migrationEnsurePdo();
