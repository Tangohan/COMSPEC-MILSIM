<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_beta_registrations (accès anticipé Overwatch) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

require_once $root . '/bootstrap/atak_beta_registrations_migration.php';
try {
    echo "Migration atak_beta_registrations (accès anticipé Overwatch)...\n";
    run_atak_beta_registrations_migration($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_beta_registrations : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_beta_registrations', $e);
}
