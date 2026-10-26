<?php

declare(strict_types=1);

/**
 * Étape « Migration theatre_mission_cycles (cycle briefing → exécution → après-action) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$theatreMissionCycleMigrate = require $root . '/bootstrap/theatre_mission_cycle_migration.php';
try {
    echo "Migration theatre_mission_cycles (cycle briefing → exécution → après-action)...\n";
    $migrationFlush();
    $theatreMissionCycleMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] theatre_mission_cycles : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('theatre_mission_cycles', $e);
}
$migrationEnsurePdo();
