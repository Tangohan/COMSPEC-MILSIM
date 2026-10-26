<?php

declare(strict_types=1);

/**
 * Étape « Migration game_phone_identity (téléphone en jeu : numéro, IMEI, MAC) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$gamePhoneIdentityMigrate = require $root . '/bootstrap/game_phone_identity_migration.php';
try {
    echo "Migration game_phone_identity (téléphone en jeu : numéro, IMEI, MAC)…\n";
    $gamePhoneIdentityMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] game_phone_identity : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('game_phone_identity', $e);
}
$migrationEnsurePdo();
