<?php

declare(strict_types=1);

/**
 * Étape « Migration personnel_correction_requests (corrections RH fiche opérateur) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$personnelCorrectionRequestsMigrate = require $root . '/bootstrap/personnel_correction_requests_migration.php';
try {
    echo "Migration personnel_correction_requests (corrections RH fiche opérateur)...\n";
    $personnelCorrectionRequestsMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] personnel_correction_requests : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('personnel_correction_requests', $e);
}
