<?php

declare(strict_types=1);

/**
 * Étape « Migrations LMS formation (première passe) OK » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$usageAnalyticsMigrate = require $root . '/bootstrap/usage_analytics_migration.php';
try {
    $usageAnalyticsMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] usage_analytics : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('usage_analytics', $e);
}
echo "Migrations LMS formation (première passe) OK.\n";
$migrationFlush();
