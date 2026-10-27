<?php

declare(strict_types=1);

/**
 * Étape « Migration training_enrollment_motivation » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$trainingEnrollmentMotivationMigrate = require $root . '/bootstrap/training_enrollment_motivation_migration.php';
try {
    $trainingEnrollmentMotivationMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] training_enrollment_motivation : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('training_enrollment_motivation', $e);
}
