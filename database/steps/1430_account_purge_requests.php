<?php

declare(strict_types=1);

/**
 * Étape « Migration account_purge_requests » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

require_once $root . '/bootstrap/account_purge_requests_migration.php';
try {
    run_account_purge_requests_migration($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] account_purge_requests : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('account_purge_requests', $e);
}
