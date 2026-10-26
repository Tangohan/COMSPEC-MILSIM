<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_donations (financement ATAK) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$atakDonationsMigrate = require $root . '/bootstrap/atak_donations_migration.php';
try {
    echo "Migration atak_donations (financement ATAK)...\n";
    $atakDonationsMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_donations : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_donations', $e);
}
$migrationEnsurePdo();
