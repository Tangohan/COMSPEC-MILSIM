<?php

declare(strict_types=1);

/**
 * Étape « Migration training_publication_engine » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$trainingPublicationEngineMigrate = require $root . '/bootstrap/training_publication_engine_migration.php';
try {
    $trainingPublicationEngineMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] training_publication_engine : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('training_publication_engine', $e);
}
