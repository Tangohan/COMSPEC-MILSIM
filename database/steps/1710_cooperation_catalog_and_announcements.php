<?php

declare(strict_types=1);

/**
 * Étape « Migration cooperation_catalog_and_announcements » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$cooperationCatalogMigrate = require $root . '/bootstrap/cooperation_catalog_and_announcements_migration.php';
try {
    $cooperationCatalogMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] cooperation_catalog_and_announcements : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('cooperation_catalog_and_announcements', $e);
}
