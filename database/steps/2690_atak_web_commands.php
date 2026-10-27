<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_web_commands (commandes web drones, charges, notifications) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$atakWebCommandsMigrate = require $root . '/bootstrap/atak_web_commands_migration.php';
try {
    echo "Migration atak_web_commands (commandes web drones, charges, notifications)…\n";
    $atakWebCommandsMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_web_commands : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_web_commands', $e);
}
$migrationEnsurePdo();
