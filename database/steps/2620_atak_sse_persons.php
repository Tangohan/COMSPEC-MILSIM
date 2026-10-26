<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_sse_persons (SSE — personnes, photos, sites, watchlist) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$atakSsePersonsMigrate = require $root . '/bootstrap/atak_sse_persons_migration.php';
try {
    echo "Migration atak_sse_persons (SSE — personnes, photos, sites, watchlist)…\n";
    $atakSsePersonsMigrate($pdo, $sseCliLog);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_sse_persons : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_sse_persons', $e);
}
$migrationEnsurePdo();
