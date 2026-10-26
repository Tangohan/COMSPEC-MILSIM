<?php

declare(strict_types=1);

/**
 * Étape « Migration brief_platform_interteam » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$briefPlatformInterteamMigrate = require $root . '/bootstrap/brief_platform_interteam_migration.php';
try {
    $briefPlatformInterteamMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] brief_platform_interteam : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('brief_platform_interteam', $e);
}
