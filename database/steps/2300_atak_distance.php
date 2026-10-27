<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_distance_stats / atak_pilot_time (distances parcourues, heures de pilote) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// App Distances de l'ATAK natif : distances parcourues par session et heures de pilote par appareil (COMSPEC Link 2.0.86).
$atakDistanceMigrate = require $root . '/bootstrap/atak_distance_migration.php';
$migrationRunner->step('atak_distance', static function () use ($atakDistanceMigrate, $pdo): void {
    echo "Migration atak_distance_stats / atak_pilot_time (distances parcourues, heures de pilote)...\n";
    $atakDistanceMigrate($pdo);
});
