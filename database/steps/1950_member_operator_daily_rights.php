<?php

declare(strict_types=1);

/**
 * Étape « Migration member_operator_daily_rights (pack Membre / Opérateur) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$memberOperatorDailyMigrate = require $root . '/bootstrap/member_operator_daily_rights_migration.php';
try {
    echo "Migration member_operator_daily_rights (pack Membre / Opérateur)...\n";
    $memberOperatorDailyMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] member_operator_daily_rights : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('member_operator_daily_rights', $e);
}
