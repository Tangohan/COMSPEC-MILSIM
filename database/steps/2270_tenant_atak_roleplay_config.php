<?php

declare(strict_types=1);

/**
 * Étape « Migration tenant_atak_roleplay (configuration roleplay — réseau, capteurs, liaison) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$tenantAtakRoleplayMigrate = require $root . '/bootstrap/tenant_atak_roleplay_config_migration.php';
try {
    echo "Migration tenant_atak_roleplay (configuration roleplay — réseau, capteurs, liaison)...\n";
    $migrationFlush();
    $tenantAtakRoleplayMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] tenant_atak_roleplay : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('tenant_atak_roleplay', $e);
}
$migrationEnsurePdo();
