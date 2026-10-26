<?php

declare(strict_types=1);

/**
 * Étape « Migration configuration post-mise à jour (system/tenant_configuration_updates) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$configurationUpdatesMigrate = require $root . '/bootstrap/configuration_updates_migration.php';
try {
    echo "Migration configuration post-mise à jour (system/tenant_configuration_updates)...\n";
    $configurationUpdatesMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] configuration_updates : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('configuration_updates', $e);
}
