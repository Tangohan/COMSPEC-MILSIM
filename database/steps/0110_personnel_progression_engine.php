<?php

declare(strict_types=1);

/**
 * Étape « Migration personnel_progression_engine » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$migrationRunner->step('run_personnel_progression_engine_migration', static fn () => run_personnel_progression_engine_migration($pdo));
