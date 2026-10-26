<?php

declare(strict_types=1);

/**
 * Étape « Migration cron_system » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$cronSystemMigrate = require $root . '/bootstrap/cron_system_migration.php';
try {
    $cronSystemMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] cron_system : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('cron_system', $e);
}
