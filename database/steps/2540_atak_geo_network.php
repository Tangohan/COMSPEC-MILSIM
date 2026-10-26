<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_geo_network (lieux nommés et segments routiers) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$atakGeoNetworkMigrate = require $root . '/bootstrap/atak_geo_network_migration.php';
try {
  echo "Migration atak_geo_network (lieux nommés et segments routiers)...\n";
  $atakGeoNetworkMigrate($pdo);
} catch (Throwable $e) {
  echo '  [ATTENTION] atak_geo_network : ' . $e->getMessage() . "\n";
  $migrationRunner->warn('atak_geo_network', $e);
}
$migrationEnsurePdo();
