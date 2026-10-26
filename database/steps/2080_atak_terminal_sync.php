<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_terminal_sync (centre de liaison) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

require_once $root . '/bootstrap/atak_terminal_sync_migration.php';
try {
    echo "Migration atak_terminal_sync (centre de liaison)...\n";
    run_atak_terminal_sync_migration($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_terminal_sync : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_terminal_sync', $e);
}
