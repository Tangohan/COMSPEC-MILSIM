<?php

declare(strict_types=1);

/**
 * Étape « Migration interteam_mission_operational_workflow » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$interteamOperationalWorkflowMigrate = require $root . '/bootstrap/interteam_mission_operational_workflow_migration.php';
try {
    $interteamOperationalWorkflowMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] interteam_mission_operational_workflow : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('interteam_mission_operational_workflow', $e);
}
