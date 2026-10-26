<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_overwatch_intel (acquittement des alertes, anneaux Géoloc) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$atakOverwatchIntelMigrate = require $root . '/bootstrap/atak_overwatch_intel_migration.php';
try {
    echo "Migration atak_overwatch_intel (acquittement des alertes, anneaux Géoloc)…\n";
    $atakOverwatchIntelMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_overwatch_intel : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_overwatch_intel', $e);
}
$migrationEnsurePdo();
