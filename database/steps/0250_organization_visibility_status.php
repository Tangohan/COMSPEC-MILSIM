<?php

declare(strict_types=1);

/**
 * Étape « Migration organization_visibility_status » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$migrationRunner->step('run_organization_visibility_status_migration', static fn () => run_organization_visibility_status_migration($pdo));
