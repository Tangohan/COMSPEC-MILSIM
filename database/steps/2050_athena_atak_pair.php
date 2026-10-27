<?php

declare(strict_types=1);

/**
 * Étape « Migration game_atak_pair_challenges (code affiché dans Arma) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$atakPairMigrate = require $root . '/bootstrap/athena_atak_pair_migration.php';
try {
    echo "Migration game_atak_pair_challenges (code affiché dans Arma)...\n";
    $atakPairMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] game_atak_pair_challenges : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('game_atak_pair_challenges', $e);
}
