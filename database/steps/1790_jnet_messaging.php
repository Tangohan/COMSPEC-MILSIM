<?php

declare(strict_types=1);

/**
 * Étape « Migration jnet_messaging » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$jnetMessagingMigrate = require $root . '/bootstrap/jnet_messaging_migration.php';
try {
    $jnetMessagingMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] jnet_messaging : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('jnet_messaging', $e);
}
