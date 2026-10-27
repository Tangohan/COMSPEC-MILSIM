<?php

declare(strict_types=1);

/**
 * Étape « Migration user_ui_tours » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$migrationRunner->step('run_user_ui_tours_migration', static fn () => run_user_ui_tours_migration($pdo));
