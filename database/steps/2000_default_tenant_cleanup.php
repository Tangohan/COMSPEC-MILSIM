<?php

declare(strict_types=1);

/**
 * Étape « Nettoyage default_tenant_cleanup (artefacts du tenant système) » (Maintenance).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$defaultTenantCleanupMigrate = require $root . '/bootstrap/default_tenant_cleanup_migration.php';
try {
    echo "Nettoyage default_tenant_cleanup (artefacts du tenant système)...\n";
    $defaultTenantCleanupMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] default_tenant_cleanup : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('default_tenant_cleanup', $e);
}
