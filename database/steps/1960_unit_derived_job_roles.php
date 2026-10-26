<?php

declare(strict_types=1);

/**
 * Étape « Migration unit_derived_job_roles (schéma emplois, sans recréer le référentiel) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$unitDerivedJobRolesMigrate = require $root . '/bootstrap/unit_derived_job_roles_migration.php';
try {
    echo "Migration unit_derived_job_roles (schéma emplois, sans recréer le référentiel)...\n";
    $unitDerivedJobRolesMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] unit_derived_job_roles : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('unit_derived_job_roles', $e);
}
