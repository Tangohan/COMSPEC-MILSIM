<?php

declare(strict_types=1);

/**
 * Étape « Migration sse_document_prefabs (SSE — présentation des documents) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$sseDocumentPrefabsMigrate = require $root . '/bootstrap/sse_document_prefabs_migration.php';
try {
    echo "Migration sse_document_prefabs (SSE — présentation des documents)…\n";
    $sseDocumentPrefabsMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] sse_document_prefabs : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('sse_document_prefabs', $e);
}
$migrationEnsurePdo();
