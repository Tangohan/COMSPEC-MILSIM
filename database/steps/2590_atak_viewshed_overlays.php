<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_viewshed_overlays (calques de vue terrain) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$atakViewshedMigrate = require $root . '/bootstrap/atak_viewshed_overlays_migration.php';
try {
    echo "Migration atak_viewshed_overlays (calques de vue terrain)...\n";
    $atakViewshedMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_viewshed_overlays : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_viewshed_overlays', $e);
}
$migrationEnsurePdo();
