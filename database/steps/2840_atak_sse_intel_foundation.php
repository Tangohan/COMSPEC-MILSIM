<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_sse_intel_foundation (SSE — LOT 1 Intelligence Workspace) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$atakSseIntelFoundationMigrate = require $root . '/bootstrap/atak_sse_intel_foundation_migration.php';
try {
    echo "Migration atak_sse_intel_foundation (SSE — LOT 1 Intelligence Workspace)…\n";
    $atakSseIntelFoundationMigrate($pdo, $sseCliLog);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_sse_intel_foundation : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_sse_intel_foundation', $e);
}
$migrationEnsurePdo();
