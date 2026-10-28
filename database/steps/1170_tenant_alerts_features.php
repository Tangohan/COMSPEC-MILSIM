<?php

declare(strict_types=1);

/**
 * Étape « Migration tenant_alerts_features » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$tenantAlertsFeaturesMigrate = require $root . '/bootstrap/tenant_alerts_features_migration.php';
try {
    $tenantAlertsFeaturesMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] tenant_alerts_features : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('tenant_alerts_features', $e);
}
