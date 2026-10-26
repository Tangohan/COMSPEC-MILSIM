<?php

declare(strict_types=1);

/**
 * Étape « Migration tenant_atak_maintenance (mode maintenance ATAK / Tacmap) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$tenantAtakMaintenanceMigrate = require $root . '/bootstrap/tenant_atak_maintenance_migration.php';
try {
    echo "Migration tenant_atak_maintenance (mode maintenance ATAK / Tacmap)...\n";
    $tenantAtakMaintenanceMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] tenant_atak_maintenance : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('tenant_atak_maintenance', $e);
}
