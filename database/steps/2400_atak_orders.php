<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_orders (ordres C2 Tacmap) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$atakOrdersMigrate = require $root . '/bootstrap/atak_orders_migration.php';
try {
    echo "Migration atak_orders (ordres C2 Tacmap)...\n";
    $atakOrdersMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_orders : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_orders', $e);
}
$migrationEnsurePdo();
