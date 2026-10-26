<?php

declare(strict_types=1);

/**
 * Étape « Migration personnel_qualifications_training_link » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$personnelQualificationsTrainingLinkMigrate = require $root . '/bootstrap/personnel_qualifications_training_link_migration.php';
try {
    $personnelQualificationsTrainingLinkMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] personnel_qualifications_training_link : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('personnel_qualifications_training_link', $e);
}
