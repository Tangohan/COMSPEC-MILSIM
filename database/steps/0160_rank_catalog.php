<?php

declare(strict_types=1);

/**
 * Étape « Migration rank_catalog » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$migrationRunner->step('run_rank_catalog_migration', static fn () => run_rank_catalog_migration($pdo));
