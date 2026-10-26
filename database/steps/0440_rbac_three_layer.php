<?php

declare(strict_types=1);

/**
 * Étape « Migration rbac_three_layer » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$migrationRunner->step('run_rbac_three_layer_migration', static fn () => run_rbac_three_layer_migration($pdo));
