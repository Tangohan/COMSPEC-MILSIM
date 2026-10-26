<?php

declare(strict_types=1);

/**
 * Étape « Migration rh_dossier_individuel (documents, mobilité, vivier, offboarding v2) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$rhDossierIndividuelMigrate = require $root . '/bootstrap/rh_dossier_individuel_migration.php';
try {
    echo "Migration rh_dossier_individuel (documents, mobilité, vivier, offboarding v2)...\n";
    $rhDossierIndividuelMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] rh_dossier_individuel : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('rh_dossier_individuel', $e);
}
