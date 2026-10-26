<?php

declare(strict_types=1);

/**
 * Étape « Migration member_departures » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$memberDeparturesMigrate = require $root . '/bootstrap/member_departures_migration.php';
try {
    $memberDeparturesMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] member_departures : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('member_departures', $e);
}
