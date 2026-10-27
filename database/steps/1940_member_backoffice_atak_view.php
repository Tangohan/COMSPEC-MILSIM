<?php

declare(strict_types=1);

/**
 * Étape « Migration member_backoffice_atak_view (consultation personnelle ATAK) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$memberBackofficeAtakMigrate = require $root . '/bootstrap/member_backoffice_atak_view_migration.php';
try {
    echo "Migration member_backoffice_atak_view (consultation personnelle ATAK)...\n";
    $memberBackofficeAtakMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] member_backoffice_atak_view : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('member_backoffice_atak_view', $e);
}
