<?php

declare(strict_types=1);

/**
 * Étape « Migration training_groups (cohortes de formation) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$trainingGroupsMigrate = require $root . '/bootstrap/training_groups_migration.php';
try {
    echo "Migration training_groups (cohortes de formation)...\n";
    $trainingGroupsMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] training_groups : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('training_groups', $e);
}
$migrationEnsurePdo();
