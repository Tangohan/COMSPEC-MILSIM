<?php

declare(strict_types=1);

/**
 * Étape « Migration permissions_action » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$migrationRunner->step('run_permissions_action_migration', static fn () => run_permissions_action_migration($pdo));
