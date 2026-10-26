<?php

declare(strict_types=1);

/**
 * Étape « Migration arma_playtime » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$migrationRunner->step('run_arma_playtime_migration', static fn () => run_arma_playtime_migration($pdo));
