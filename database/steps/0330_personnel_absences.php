<?php

declare(strict_types=1);

/**
 * Étape « Migration personnel_absences » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$migrationRunner->step('run_personnel_absences_migration', static fn () => run_personnel_absences_migration($pdo));
