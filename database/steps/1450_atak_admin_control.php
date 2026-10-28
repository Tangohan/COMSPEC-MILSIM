<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_admin_control » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

require_once $root . '/bootstrap/atak_admin_control_migration.php';
try {
    run_atak_admin_control_migration($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_admin_control : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_admin_control', $e);
}
