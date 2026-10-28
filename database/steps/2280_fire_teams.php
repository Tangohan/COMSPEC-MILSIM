<?php

declare(strict_types=1);

/**
 * Étape « Migration fire_teams (équipes de feu mission + organigramme) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$fireTeamsMigrate = require $root . '/bootstrap/fire_teams_migration.php';
try {
    echo "Migration fire_teams (équipes de feu mission + organigramme)...\n";
    $migrationFlush();
    $fireTeamsMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] fire_teams : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('fire_teams', $e);
}
$migrationEnsurePdo();
