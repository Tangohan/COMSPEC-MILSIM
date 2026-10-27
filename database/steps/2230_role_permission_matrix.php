<?php

declare(strict_types=1);

/**
 * Étape « Migration role_permission_matrix (matrice rôles & permissions par module) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$rolePermissionMatrixMigrate = require $root . '/bootstrap/role_permission_matrix_migration.php';
try {
    echo "Migration role_permission_matrix (matrice rôles & permissions par module)...\n";
    $migrationFlush();
    $rolePermissionMatrixMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] role_permission_matrix : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('role_permission_matrix', $e);
}
$migrationEnsurePdo();
