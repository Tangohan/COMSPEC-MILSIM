<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_iceman_reports (types Reports ATAK Enhanced) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$atakIcemanReportsMigrate = require $root . '/bootstrap/atak_iceman_reports_migration.php';
try {
    echo "Migration atak_iceman_reports (types Reports ATAK Enhanced)…\n";
    $atakIcemanReportsMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_iceman_reports : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_iceman_reports', $e);
}
$migrationEnsurePdo();
