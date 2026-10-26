<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_map_gateway (passerelle ATAK inter-équipes) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

require_once $root . '/bootstrap/atak_map_gateway_migration.php';
try {
    echo "Migration atak_map_gateway (passerelle ATAK inter-équipes)...\n";
    run_atak_map_gateway_migration($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_map_gateway : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_map_gateway', $e);
}
