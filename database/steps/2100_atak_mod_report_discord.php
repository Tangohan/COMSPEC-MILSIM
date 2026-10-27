<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_mod_report_discord (tickets Discord des signalements du mod) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

require_once $root . '/bootstrap/atak_mod_report_discord_migration.php';
try {
    echo "Migration atak_mod_report_discord (tickets Discord des signalements du mod)...\n";
    run_atak_mod_report_discord_migration($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_mod_report_discord : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_mod_report_discord', $e);
}
