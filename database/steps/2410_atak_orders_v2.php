<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_orders v2 (destinataires, ACK, délais radio, ID militaire) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$atakOrdersV2Migrate = require $root . '/bootstrap/atak_orders_v2_migration.php';
try {
    echo "Migration atak_orders v2 (destinataires, ACK, délais radio, ID militaire)...\n";
    $atakOrdersV2Migrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_orders_v2 : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_orders_v2', $e);
}
$migrationEnsurePdo();
