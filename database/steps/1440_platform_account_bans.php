<?php

declare(strict_types=1);

/**
 * Étape « Migration platform_account_bans » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

require_once $root . '/bootstrap/platform_account_bans_migration.php';
try {
    run_platform_account_bans_migration($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] platform_account_bans : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('platform_account_bans', $e);
}
