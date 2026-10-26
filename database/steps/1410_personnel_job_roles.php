<?php

declare(strict_types=1);

/**
 * Étape « Migration personnel_job_roles » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$personnelJobRolesMigrate = require $root . '/bootstrap/personnel_job_roles_migration.php';
$migrationRunner->step('personnelJobRolesMigrate', static fn () => $personnelJobRolesMigrate($pdo));
