<?php

declare(strict_types=1);

/**
 * Étape « Migration user_advanced_edit_grants » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$migrationRunner->step('run_user_advanced_edit_grants_migration', static fn () => run_user_advanced_edit_grants_migration($pdo));
