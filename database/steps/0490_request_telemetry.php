<?php

declare(strict_types=1);

/**
 * Étape « Migration request_telemetry » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$migrationRunner->step('run_request_telemetry_migration', static fn () => run_request_telemetry_migration($pdo));
migratePlatformAdminTenantIntervention($pdo);
