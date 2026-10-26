<?php

declare(strict_types=1);

/**
 * Étape « Migration training_enrollment_withdrawn_status » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$trainingEnrollmentWithdrawnMigrate = require $root . '/bootstrap/training_enrollment_withdrawn_status_migration.php';
try {
    $trainingEnrollmentWithdrawnMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] training_enrollment_withdrawn : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('training_enrollment_withdrawn', $e);
}
