<?php

declare(strict_types=1);

/**
 * Étape « Migration transactional_email » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

require_once $root . '/bootstrap/transactional_email_migration.php';
$migrationRunner->step('run_transactional_email_migration', static fn () => run_transactional_email_migration($pdo));
