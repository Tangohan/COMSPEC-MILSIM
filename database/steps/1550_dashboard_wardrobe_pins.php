<?php

declare(strict_types=1);

/**
 * Étape « Migration dashboard_wardrobe_pins » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$dashboardWardrobePinsMigrate = require $root . '/bootstrap/dashboard_wardrobe_pins_migration.php';
try {
    $dashboardWardrobePinsMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] tenant_dashboard_wardrobe_pins : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('tenant_dashboard_wardrobe_pins', $e);
}
