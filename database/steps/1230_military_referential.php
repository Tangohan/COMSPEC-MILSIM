<?php

declare(strict_types=1);

/**
 * Étape « Migration référentiel militaire SOF (countries / military_units / affiliations) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$militaryReferentialMigrate = require $root . '/bootstrap/military_referential_migration.php';
try {
    echo "Migration référentiel militaire SOF (countries / military_units / affiliations)...\n";
    $militaryReferentialMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] military_referential : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('military_referential', $e);
}
