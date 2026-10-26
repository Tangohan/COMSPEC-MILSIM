<?php

declare(strict_types=1);

/**
 * Étape « Migration hr_charter_lms » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$migrationRunner->step('run_hr_charter_lms_migration', static fn () => run_hr_charter_lms_migration($pdo));
