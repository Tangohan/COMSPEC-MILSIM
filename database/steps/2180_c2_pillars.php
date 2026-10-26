<?php

declare(strict_types=1);

/**
 * Étape « Migration c2_pillars (appui-feu, zones danger, logistique, intel, replay, IFF) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$c2PillarsMigrate = require $root . '/bootstrap/c2_pillars_migration.php';
try {
    echo "Migration c2_pillars (appui-feu, zones danger, logistique, intel, replay, IFF)...\n";
    $migrationFlush();
    $c2PillarsMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] c2_pillars : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('c2_pillars', $e);
}
$migrationEnsurePdo();
