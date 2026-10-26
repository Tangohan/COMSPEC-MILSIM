<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_unit_motion (cinématique BFT et destinations) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$atakUnitMotionMigrate = require $root . '/bootstrap/atak_unit_motion_migration.php';
try {
  echo "Migration atak_unit_motion (cinématique BFT et destinations)...\n";
  $atakUnitMotionMigrate($pdo);
} catch (Throwable $e) {
  echo '  [ATTENTION] atak_unit_motion : ' . $e->getMessage() . "\n";
  $migrationRunner->warn('atak_unit_motion', $e);
}
$migrationEnsurePdo();
