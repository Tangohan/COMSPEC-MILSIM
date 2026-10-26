<?php

declare(strict_types=1);

/**
 * Étape « Migration tenant_dashboard_pins » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$tenantDashboardPinsMigrate = require $root . '/bootstrap/tenant_dashboard_pins_migration.php';
try {
    $tenantDashboardPinsMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] tenant_dashboard_pins : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('tenant_dashboard_pins', $e);
}
