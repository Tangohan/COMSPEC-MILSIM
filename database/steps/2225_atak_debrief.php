<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_debrief (CR de fin d'opération imposé sur les ATAK) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$migrationRunner->step('atak_debrief', static function () use ($root, $pdo): void {
    echo "Migration atak_debrief (CR de fin d'opération imposé sur les ATAK)...\n";
    require_once $root . '/bootstrap/atak_debrief_migration.php';
    run_atak_debrief_migration($pdo);
});
$migrationEnsurePdo();
