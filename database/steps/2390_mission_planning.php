<?php

declare(strict_types=1);

/**
 * Étape « Migration mission_planning (planification / organisation de combat) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$missionPlanningMigrate = require $root . '/bootstrap/mission_planning_migration.php';
try {
    echo "Migration mission_planning (planification / organisation de combat)...\n";
    $migrationFlush();
    $missionPlanningMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] mission_planning : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('mission_planning', $e);
}
$migrationEnsurePdo();
