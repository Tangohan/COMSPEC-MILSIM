<?php

declare(strict_types=1);

/**
 * Étape « Migration intel_intake (remontées : suivi, fil, caviardage, lectures, pièces floutées) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Back-office Remontées : suivi des comptes rendus et des fiches de renseignement (fil, attribution, caviardage, lectures).
$intelIntakeMigrate = require $root . '/bootstrap/intel_intake_migration.php';
$migrationRunner->step('intel_intake', static function () use ($intelIntakeMigrate, $pdo): void {
    echo "Migration intel_intake (remontées : suivi, fil, caviardage, lectures, pièces floutées)...\n";
    $intelIntakeMigrate($pdo);
});
$migrationEnsurePdo();
