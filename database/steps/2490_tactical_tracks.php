<?php

declare(strict_types=1);

/**
 * Étape « Migration tactical_tracks (tracks COP Phase D) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$tacticalTracksMigrate = require $root . '/bootstrap/tactical_tracks_migration.php';
try {
  echo "Migration tactical_tracks (tracks COP Phase D)...\n";
  $tacticalTracksMigrate($pdo);
} catch (Throwable $e) {
  echo '  [ATTENTION] tactical_tracks : ' . $e->getMessage() . "\n";
  $migrationRunner->warn('tactical_tracks', $e);
}
$migrationEnsurePdo();
