<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_rf_hits_lot1 (Fieldwatch — hits RF passifs) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$atakRfHitsLot1Migrate = require $root . '/bootstrap/atak_rf_hits_lot1_migration.php';
try {
  echo "Migration atak_rf_hits_lot1 (Fieldwatch — hits RF passifs)...\n";
  $atakRfHitsLot1Migrate($pdo);
} catch (Throwable $e) {
  echo '  [ATTENTION] atak_rf_hits_lot1 : ' . $e->getMessage() . "\n";
  $migrationRunner->warn('atak_rf_hits_lot1', $e);
}
$migrationEnsurePdo();
