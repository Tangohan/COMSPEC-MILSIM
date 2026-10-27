<?php

declare(strict_types=1);

/**
 * Étape « Migration units public dates (création / date complémentaire) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$unitsPublicDatesMigrate = require $root . '/bootstrap/units_public_dates_migration.php';
try {
    echo "Migration units public dates (création / date complémentaire)...\n";
    $unitsPublicDatesMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] units_public_dates : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('units_public_dates', $e);
}
