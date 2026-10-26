<?php

declare(strict_types=1);

/**
 * Étape « Migration tenant_atak_access_key (clé d’accès Overwatch par communauté) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$tenantAtakAccessKeyMigrate = require $root . '/bootstrap/tenant_atak_access_key_migration.php';
try {
    echo "Migration tenant_atak_access_key (clé d’accès Overwatch par communauté)...\n";
    $tenantAtakAccessKeyMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] tenant_atak_access_key : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('tenant_atak_access_key', $e);
}
