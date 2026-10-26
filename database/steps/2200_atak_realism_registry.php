<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_realism_registry (terminaux ATAK, certificats, rattachements opérateur) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$atakRealismRegistryMigrate = require $root . '/bootstrap/atak_realism_registry_migration.php';
try {
    echo "Migration atak_realism_registry (terminaux ATAK, certificats, rattachements opérateur)...\n";
    $migrationFlush();
    $atakRealismRegistryMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_realism_registry : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_realism_registry', $e);
}
$migrationEnsurePdo();
