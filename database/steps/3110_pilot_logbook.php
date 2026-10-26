<?php

declare(strict_types=1);

/**
 * Étape « Carnet de vol des pilotes » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Carnet de vol : vols relevés par le mod et vols saisis par le pilote, contresignés par un instructeur.
$pilotLogbook = require $root . '/bootstrap/pilot_logbook_migration.php';
$migrationRunner->step('pilot_logbook', static function () use ($pilotLogbook, $pdo): void {
    $pilotLogbook($pdo);
});
