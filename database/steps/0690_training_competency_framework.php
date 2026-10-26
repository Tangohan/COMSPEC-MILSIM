<?php

declare(strict_types=1);

/**
 * Étape « Migration training_competency_framework » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$trainingCompetencyFrameworkMigrate = require $root . '/bootstrap/training_competency_framework_migration.php';
try {
    $trainingCompetencyFrameworkMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] training_competency_framework : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('training_competency_framework', $e);
}
