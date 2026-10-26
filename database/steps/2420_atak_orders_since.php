<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_orders since (updated_at + index poll delta) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$atakOrdersSinceMigrate = require $root . '/bootstrap/atak_orders_since_migration.php';
try {
    echo "Migration atak_orders since (updated_at + index poll delta)...\n";
    $atakOrdersSinceMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_orders_since : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_orders_since', $e);
}
$migrationEnsurePdo();
