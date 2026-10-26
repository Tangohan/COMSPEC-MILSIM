<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_device_logs (journal des appareils ATAK) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

require_once $root . '/bootstrap/atak_device_logs_migration.php';
try {
    echo "Migration atak_device_logs (journal des appareils ATAK)...\n";
    run_atak_device_logs_migration($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_device_logs : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_device_logs', $e);
}
