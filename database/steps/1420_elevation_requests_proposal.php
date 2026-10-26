<?php

declare(strict_types=1);

/**
 * Étape « Migration elevation_requests_proposal » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$elevationRequestsProposalMigrate = require $root . '/bootstrap/elevation_requests_proposal_migration.php';
try {
    $elevationRequestsProposalMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] elevation_requests_proposal : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('elevation_requests_proposal', $e);
}
