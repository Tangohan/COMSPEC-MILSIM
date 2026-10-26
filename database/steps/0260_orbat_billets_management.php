<?php

declare(strict_types=1);

/**
 * Étape « Migration orbat_billets_management » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$migrationRunner->step('run_orbat_billets_management_migration', static fn () => run_orbat_billets_management_migration($pdo));
