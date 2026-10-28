<?php

declare(strict_types=1);

/**
 * Étape « Migration users_platform_admin_flag » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

try {
    run_users_platform_admin_flag_migration($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] users_platform_admin_flag : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('users_platform_admin_flag', $e);
}
