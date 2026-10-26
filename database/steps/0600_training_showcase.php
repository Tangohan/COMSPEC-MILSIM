<?php

declare(strict_types=1);

/**
 * Étape « Migration training_showcase » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$trainingShowcaseMigrateEarly = require $root . '/bootstrap/training_showcase_migration.php';
$migrationRunner->step('trainingShowcaseMigrateEarly', static fn () => $trainingShowcaseMigrateEarly($pdo));
