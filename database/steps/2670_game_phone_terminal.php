<?php

declare(strict_types=1);

/**
 * Étape « Migration game_phone_terminal (paramètres des terminaux : batterie, type de numéro) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$gamePhoneTerminalMigrate = require $root . '/bootstrap/game_phone_terminal_migration.php';
try {
    echo "Migration game_phone_terminal (paramètres des terminaux : batterie, type de numéro)…\n";
    $gamePhoneTerminalMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] game_phone_terminal : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('game_phone_terminal', $e);
}
$migrationEnsurePdo();
