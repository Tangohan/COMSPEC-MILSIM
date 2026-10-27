<?php

declare(strict_types=1);

/**
 * Étape « Migration interteam_cooperation_hub » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$interteamCoopHubMigrate = require $root . '/bootstrap/interteam_cooperation_hub_migration.php';
try {
    $interteamCoopHubMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] interteam_cooperation_hub : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('interteam_cooperation_hub', $e);
}
