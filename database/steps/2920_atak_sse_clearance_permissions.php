<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_sse_clearance_permissions (SSE — habilitations de lecture) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$atakSseClearancePermsMigrate = require $root . '/bootstrap/atak_sse_clearance_permissions_migration.php';
try {
    echo "Migration atak_sse_clearance_permissions (SSE — habilitations de lecture)…\n";
    $atakSseClearancePermsMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_sse_clearance_permissions : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_sse_clearance_permissions', $e);
}
$migrationEnsurePdo();
