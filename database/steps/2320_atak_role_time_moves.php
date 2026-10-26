<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_role_time_moves (corrections du temps par rôle) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Corrections du temps par rôle par l'encadrement (déplacer vers le bon rôle, journal annulable).
$migrationRunner->step('atak_role_time_moves', static function () use ($pdo): void {
    echo "Migration atak_role_time_moves (corrections du temps par rôle)...\n";
    $pdo->exec(\App\Repositories\AtakRoleTimeCorrectionRepository::createTableSql());
});
