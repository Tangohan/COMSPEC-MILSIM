<?php

declare(strict_types=1);

/**
 * Étape « Migration operator_game_registry » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$migrationRunner->step('run_operator_game_registry_migration', static fn () => run_operator_game_registry_migration($pdo));
