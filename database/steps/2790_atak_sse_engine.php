<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_sse_engine (SSE — file suggestions + signaux moteur) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$atakSseEngineMigrate = require $root . '/bootstrap/atak_sse_engine_migration.php';
try {
    echo "Migration atak_sse_engine (SSE — file suggestions + signaux moteur)…\n";
    $atakSseEngineMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_sse_engine : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_sse_engine', $e);
}
$migrationEnsurePdo();
