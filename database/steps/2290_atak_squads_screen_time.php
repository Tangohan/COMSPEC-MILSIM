<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_squads / atak_screen_time (escouades en jeu, temps d » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Escouades et équipes de feu remontées du jeu, temps d'écran et temps par rôle (ATAK natif, COMSPEC Link 2.0.63).
$atakSquadsMigrate = require $root . '/bootstrap/atak_squads_screen_time_migration.php';
$migrationRunner->step('atak_squads_screen_time', static function () use ($atakSquadsMigrate, $pdo): void {
    echo "Migration atak_squads / atak_screen_time (escouades en jeu, temps d'écran et par rôle)...\n";
    $atakSquadsMigrate($pdo);
});
