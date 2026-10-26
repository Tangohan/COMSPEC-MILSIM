<?php

declare(strict_types=1);

/**
 * Étape « Migration training_formation_custom_pages » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$trainingFormationCustomPagesMigrate = require $root . '/bootstrap/training_formation_custom_pages_migration.php';
try {
    $trainingFormationCustomPagesMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] training_formation_custom_pages : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('training_formation_custom_pages', $e);
}
