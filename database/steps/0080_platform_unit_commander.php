<?php

declare(strict_types=1);

/**
 * Étape « Migration platform_unit_commander » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$migrationRunner->step('run_platform_unit_commander_migration', static fn () => run_platform_unit_commander_migration($pdo));
