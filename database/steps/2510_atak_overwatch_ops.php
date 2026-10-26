<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_overwatch_ops (relais ATAK et débit de remontée) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$atakOverwatchOpsMigrate = require $root . '/bootstrap/atak_overwatch_ops_migration.php';
try {
  echo "Migration atak_overwatch_ops (relais ATAK et débit de remontée)...\n";
  $atakOverwatchOpsMigrate($pdo);
} catch (Throwable $e) {
  echo '  [ATTENTION] atak_overwatch_ops : ' . $e->getMessage() . "\n";
  $migrationRunner->warn('atak_overwatch_ops', $e);
}
$migrationEnsurePdo();
