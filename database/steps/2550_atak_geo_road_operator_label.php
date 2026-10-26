<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_geo_road_operator_label (noms de routes au poste) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$atakGeoRoadLabelMigrate = require $root . '/bootstrap/atak_geo_road_operator_label_migration.php';
try {
  echo "Migration atak_geo_road_operator_label (noms de routes au poste)...\n";
  $atakGeoRoadLabelMigrate($pdo);
} catch (Throwable $e) {
  echo '  [ATTENTION] atak_geo_road_operator_label : ' . $e->getMessage() . "\n";
  $migrationRunner->warn('atak_geo_road_operator_label', $e);
}
$migrationEnsurePdo();
