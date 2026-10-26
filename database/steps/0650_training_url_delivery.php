<?php

declare(strict_types=1);

/**
 * Étape « Migration training_url_delivery » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$trainingUrlDeliveryMigrate = require $root . '/bootstrap/training_url_delivery_migration.php';
try {
    $trainingUrlDeliveryMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] training_url_delivery : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('training_url_delivery', $e);
}
