<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_nine_line.mission_kind (CAS vs MEDEVAC) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$atakNineLineKindMigrate = require $root . '/bootstrap/atak_nine_line_mission_kind_migration.php';
try {
    echo "Migration atak_nine_line.mission_kind (CAS vs MEDEVAC)...\n";
    $atakNineLineKindMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_nine_line_mission_kind : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_nine_line_mission_kind', $e);
}
$migrationEnsurePdo();
