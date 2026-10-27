<?php

declare(strict_types=1);

/**
 * Étape « Migration tenant_custom_maps » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$tenantCustomMapsMigrate = require $root . '/bootstrap/tenant_custom_maps_migration.php';
try {
    $tenantCustomMapsMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] tenant_custom_maps : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('tenant_custom_maps', $e);
}
