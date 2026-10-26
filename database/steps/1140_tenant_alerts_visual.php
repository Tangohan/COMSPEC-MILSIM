<?php

declare(strict_types=1);

/**
 * Étape « Migration tenant_alerts_visual » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$tenantAlertsVisualMigrate = require $root . '/bootstrap/tenant_alerts_visual_migration.php';
try {
    $tenantAlertsVisualMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] tenant_alerts_visual : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('tenant_alerts_visual', $e);
}
