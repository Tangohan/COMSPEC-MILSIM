<?php

declare(strict_types=1);

/**
 * Étape « Migration personnel_role_consolidation (fusion rôle métier / primary_role / secondary_role) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$personnelRoleConsolidationMigrate = require $root . '/bootstrap/personnel_role_consolidation_migration.php';
try {
    echo "Migration personnel_role_consolidation (fusion rôle métier / primary_role / secondary_role)...\n";
    $personnelRoleConsolidationMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] personnel_role_consolidation : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('personnel_role_consolidation', $e);
}
