<?php

declare(strict_types=1);

/**
 * Étape « Migration tactical_game_link (code de liaison Arma) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$tacticalGameLinkMigrate = require $root . '/bootstrap/tactical_game_link_migration.php';
try {
    echo "Migration tactical_game_link (code de liaison Arma)...\n";
    $tacticalGameLinkMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] tactical_game_link : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('tactical_game_link', $e);
}
