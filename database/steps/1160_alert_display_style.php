<?php

declare(strict_types=1);

/**
 * Étape « Migration alert_display_style » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$alertDisplayStyleMigrate = require $root . '/bootstrap/alert_display_style_migration.php';
try {
    $alertDisplayStyleMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] alert_display_style : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('alert_display_style', $e);
}
