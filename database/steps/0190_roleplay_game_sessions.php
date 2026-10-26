<?php

declare(strict_types=1);

/**
 * Étape « Migration roleplay_game_sessions » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$migrationRunner->step('run_roleplay_game_sessions_migration', static fn () => run_roleplay_game_sessions_migration($pdo));
