<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_cop_terrain (relief de théâtre et événements d’analyse) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$atakCopTerrainMigrate = require $root . '/bootstrap/atak_cop_terrain_migration.php';
try {
  echo "Migration atak_cop_terrain (relief de théâtre et événements d’analyse)...\n";
  $atakCopTerrainMigrate($pdo);
} catch (Throwable $e) {
  echo '  [ATTENTION] atak_cop_terrain : ' . $e->getMessage() . "\n";
  $migrationRunner->warn('atak_cop_terrain', $e);
}
$migrationEnsurePdo();
