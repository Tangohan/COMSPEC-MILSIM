<?php

declare(strict_types=1);

/**
 * Étape « Migration platform_alerts_features » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$platformAlertsFeaturesMigrate = require $root . '/bootstrap/platform_alerts_features_migration.php';
try {
    $platformAlertsFeaturesMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] platform_alerts_features : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('platform_alerts_features', $e);
}
