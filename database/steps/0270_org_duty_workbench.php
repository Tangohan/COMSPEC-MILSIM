<?php

declare(strict_types=1);

/**
 * Étape « Migration org_duty_workbench » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$migrationRunner->step('run_org_duty_workbench_migration', static fn () => run_org_duty_workbench_migration($pdo));
