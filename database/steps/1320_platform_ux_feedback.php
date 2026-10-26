<?php

declare(strict_types=1);

/**
 * Étape « Migration platform_ux_feedback (retours UI + flash_info_detailed) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$platformUxFeedbackMigrate = require $root . '/bootstrap/platform_ux_feedback_migration.php';
try {
    echo "Migration platform_ux_feedback (retours UI + flash_info_detailed)...\n";
    $platformUxFeedbackMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] platform_ux_feedback : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('platform_ux_feedback', $e);
}
