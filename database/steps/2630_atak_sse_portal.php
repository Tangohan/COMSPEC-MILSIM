<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_sse_portal (dossiers, codes d’accès, notes, preuves) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$atakSsePortalMigrate = require $root . '/bootstrap/atak_sse_portal_migration.php';
try {
    echo "Migration atak_sse_portal (dossiers, codes d’accès, notes, preuves)…\n";
    $atakSsePortalMigrate($pdo, $sseCliLog);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_sse_portal : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_sse_portal', $e);
}
$migrationEnsurePdo();
