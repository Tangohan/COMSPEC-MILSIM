<?php

declare(strict_types=1);

/**
 * Étape « Migration roleplay_bilan_cadence (date du dernier bilan RP) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$roleplayBilanCadenceMigrate = require $root . '/bootstrap/roleplay_bilan_cadence_migration.php';
try {
    echo "Migration roleplay_bilan_cadence (date du dernier bilan RP)...\n";
    $roleplayBilanCadenceMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] roleplay_bilan_cadence : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('roleplay_bilan_cadence', $e);
}
