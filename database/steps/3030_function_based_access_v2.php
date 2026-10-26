<?php

declare(strict_types=1);

/**
 * Étape « Accès par fonction (FBAC v2) — toujours en dernier » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// FBAC must run last so no legacy seed can recreate a global or role-derived grant.
$migrationRunner->step('function_based_access_v2', static fn () => run_function_based_access_v2_migration($pdo));
