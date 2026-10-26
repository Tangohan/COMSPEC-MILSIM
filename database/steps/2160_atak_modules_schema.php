<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_modules_schema (rapports, POI, zones, MEDEVAC, QRF, véhicules…) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$atakModulesSchemaMigrate = require $root . '/bootstrap/atak_modules_schema_migration.php';
try {
    echo "Migration atak_modules_schema (rapports, POI, zones, MEDEVAC, QRF, véhicules…)...\n";
    $migrationFlush();
    $atakModulesSchemaMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_modules_schema : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_modules_schema', $e);
}
$migrationEnsurePdo();
