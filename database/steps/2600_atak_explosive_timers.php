<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_explosive_timers (charges à retardement ATAK) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$atakExplosiveTimersMigrate = require $root . '/bootstrap/atak_explosive_timers_migration.php';
try {
    echo "Migration atak_explosive_timers (charges à retardement ATAK)...\n";
    $atakExplosiveTimersMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_explosive_timers : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_explosive_timers', $e);
}
$migrationEnsurePdo();
