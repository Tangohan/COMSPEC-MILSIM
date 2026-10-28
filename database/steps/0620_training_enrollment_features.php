<?php

declare(strict_types=1);

/**
 * Étape « Migration training_enrollment_features » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$trainingEnrollmentFeaturesMigrate = require $root . '/bootstrap/training_enrollment_features_migration.php';
try {
    $trainingEnrollmentFeaturesMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] training_enrollment_features : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('training_enrollment_features', $e);
}
