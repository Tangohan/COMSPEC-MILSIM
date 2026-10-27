<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_nine_line.geo_json (géométrie de l » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$atakNineLineGeoMigrate = require $root . '/bootstrap/atak_nine_line_geo_migration.php';
try {
    echo "Migration atak_nine_line.geo_json (géométrie de l'app JTAC)...\n";
    $atakNineLineGeoMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_nine_line_geo : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_nine_line_geo', $e);
}
$migrationEnsurePdo();
