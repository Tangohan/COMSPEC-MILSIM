<?php

declare(strict_types=1);

/**
 * Étape « Bibliothèque de l'éditeur SSE » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : délègue à
 * bootstrap/atak_sse_editor_library_migration.php, rejoué sans effet si la table est en place.
 */

$atakSseEditorLibraryMigrate = require $root . '/bootstrap/atak_sse_editor_library_migration.php';
try {
    echo "Migration atak_sse_editor_library (bibliothèque de l'éditeur SSE)…\n";
    $atakSseEditorLibraryMigrate($pdo, $sseCliLog);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_sse_editor_library : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_sse_editor_library', $e);
}
$migrationEnsurePdo();
