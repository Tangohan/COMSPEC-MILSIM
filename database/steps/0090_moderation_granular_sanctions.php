<?php

declare(strict_types=1);

/**
 * Étape « Migration moderation_granular_sanctions » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$migrationRunner->step('run_moderation_granular_sanctions_migration', static fn () => run_moderation_granular_sanctions_migration($pdo));
