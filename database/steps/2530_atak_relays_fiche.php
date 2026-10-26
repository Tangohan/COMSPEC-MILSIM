<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_relays_fiche (identité, débit, fiabilité, places) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$atakRelaysFicheMigrate = require $root . '/bootstrap/atak_relays_fiche_migration.php';
try {
  echo "Migration atak_relays_fiche (identité, débit, fiabilité, places)...\n";
  $atakRelaysFicheMigrate($pdo);
} catch (Throwable $e) {
  echo '  [ATTENTION] atak_relays_fiche : ' . $e->getMessage() . "\n";
  $migrationRunner->warn('atak_relays_fiche', $e);
}
$migrationEnsurePdo();
