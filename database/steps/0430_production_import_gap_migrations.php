<?php

declare(strict_types=1);

/**
 * Étape « Migration production_import_gap_migrations » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$migrationRunner->step('run_production_import_gap_migrations', static fn () => run_production_import_gap_migrations($pdo, $root));
