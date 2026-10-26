<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_marker_detection_rules (détection des marqueurs en jeu) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

require_once $root . '/bootstrap/atak_marker_detection_rules_migration.php';
try {
    echo "Migration atak_marker_detection_rules (détection des marqueurs en jeu)...\n";
    run_atak_marker_detection_rules_migration($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_marker_detection_rules : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_marker_detection_rules', $e);
}
