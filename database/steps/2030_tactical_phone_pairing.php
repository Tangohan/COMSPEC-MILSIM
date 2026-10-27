<?php

declare(strict_types=1);

/**
 * Étape « Migration tactical_phone_pairing (connexion téléphone ATAK) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$tacticalPhonePairingMigrate = require $root . '/bootstrap/tactical_phone_pairing_migration.php';
try {
    echo "Migration tactical_phone_pairing (connexion téléphone ATAK)...\n";
    $tacticalPhonePairingMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] tactical_phone_pairing : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('tactical_phone_pairing', $e);
}
