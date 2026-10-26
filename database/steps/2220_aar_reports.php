<?php

declare(strict_types=1);

/**
 * Étape « Migration aar_reports (comptes rendus post-op structurés) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$aarReportsMigrate = require $root . '/bootstrap/aar_reports_migration.php';
try {
    echo "Migration aar_reports (comptes rendus post-op structurés)...\n";
    $migrationFlush();
    $aarReportsMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] aar_reports : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('aar_reports', $e);
}
$migrationEnsurePdo();
