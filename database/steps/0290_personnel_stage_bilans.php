<?php

declare(strict_types=1);

/**
 * Étape « Migration personnel_stage_bilans » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$migrationRunner->step('run_personnel_stage_bilans_migration', static fn () => run_personnel_stage_bilans_migration($pdo));
