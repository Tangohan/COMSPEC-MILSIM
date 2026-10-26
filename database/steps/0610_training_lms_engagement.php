<?php

declare(strict_types=1);

/**
 * Étape « Migration training_lms_engagement » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$trainingLmsEngagementMigrateEarly = require $root . '/bootstrap/training_lms_engagement_migration.php';
try {
    $trainingLmsEngagementMigrateEarly($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] training_lms_engagement : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('training_lms_engagement', $e);
}
