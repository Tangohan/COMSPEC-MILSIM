<?php

declare(strict_types=1);

/**
 * Étape « Migration training_resources_library_document » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$trainingResourcesLibraryDocMigrate = require $root . '/bootstrap/training_resources_library_document_migration.php';
try {
    $trainingResourcesLibraryDocMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] training_resources_library_document : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('training_resources_library_document', $e);
}
