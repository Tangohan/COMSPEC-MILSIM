<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_units_position (coordonnées carte pos_x / pos_y) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$atakUnitsPositionMigrate = require $root . '/bootstrap/atak_units_position_migration.php';
try {
    echo "Migration atak_units_position (coordonnées carte pos_x / pos_y)...\n";
    $atakUnitsPositionMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_units_position : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_units_position', $e);
}
$migrationEnsurePdo();
