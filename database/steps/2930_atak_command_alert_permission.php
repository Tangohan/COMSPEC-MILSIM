<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_command_alert_permission (Overwatch — alerte plein écran) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$atakCommandAlertPermsMigrate = require $root . '/bootstrap/atak_command_alert_permission_migration.php';
try {
    echo "Migration atak_command_alert_permission (Overwatch — alerte plein écran)…\n";
    $atakCommandAlertPermsMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_command_alert_permission : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_command_alert_permission', $e);
}
$migrationEnsurePdo();
