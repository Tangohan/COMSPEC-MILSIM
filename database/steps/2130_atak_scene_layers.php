<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_scene_layers (obstacles linéaires du théâtre) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

require_once $root . '/bootstrap/atak_scene_layers_migration.php';
try {
    echo "Migration atak_scene_layers (obstacles linéaires du théâtre)...\n";
    run_atak_scene_layers_migration($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_scene_layers : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_scene_layers', $e);
}
