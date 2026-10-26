<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_terminal_phone_type (terminaux du jeu en téléphone, séries et empreintes des certificats) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$atakTerminalPhoneTypeMigrate = require $root . '/bootstrap/atak_terminal_phone_type_migration.php';
try {
    echo "Migration atak_terminal_phone_type (terminaux du jeu en téléphone, séries et empreintes des certificats)…\n";
    $atakTerminalPhoneTypeMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_terminal_phone_type : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_terminal_phone_type', $e);
}
$migrationEnsurePdo();
