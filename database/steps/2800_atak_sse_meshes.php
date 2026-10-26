<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_sse_meshes (SSE — toiles de données) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$atakSseMeshesMigrate = require $root . '/bootstrap/atak_sse_meshes_migration.php';
try {
    echo "Migration atak_sse_meshes (SSE — toiles de données)…\n";
    $atakSseMeshesMigrate($pdo, $sseCliLog);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_sse_meshes : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_sse_meshes', $e);
}
$migrationEnsurePdo();
