<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_garmin_days / atak_garmin_activities / atak_garmin_status (montre de l » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// App Garmin de l'ATAK natif : montre du joueur par jour, activités et dernier état (COMSPEC Link 2.0.87).
$atakGarminMigrate = require $root . '/bootstrap/atak_garmin_migration.php';
$migrationRunner->step('atak_garmin', static function () use ($atakGarminMigrate, $pdo): void {
    echo "Migration atak_garmin_days / atak_garmin_activities / atak_garmin_status (montre de l'app Garmin)...\n";
    $atakGarminMigrate($pdo);
});
