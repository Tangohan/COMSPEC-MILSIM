<?php

declare(strict_types=1);

/**
 * Étape « Migration JNET (origine des échanges : reco, photos et rapports du jeu) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$jnetExchangesSourceMigrate = require $root . '/bootstrap/jnet_exchanges_source_migration.php';
try {
    echo "Migration JNET (origine des échanges : reco, photos et rapports du jeu)...\n";
    $jnetExchangesSourceMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] jnet_exchanges_source : ' . $e->getMessage() . "\n";
}
