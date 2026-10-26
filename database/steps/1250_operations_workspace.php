<?php

declare(strict_types=1);

/**
 * Étape « Migration espaces opérationnels (operations, calques, tâches, objectifs) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$operationsWorkspaceMigrate = require $root . '/bootstrap/operations_workspace_migration.php';
try {
    echo "Migration espaces opérationnels (operations, calques, tâches, objectifs)...\n";
    $operationsWorkspaceMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] operations_workspace : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('operations_workspace', $e);
}
