<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_sse_text_library (SSE — bibliothèque rédactionnelle) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$atakSseTextLibraryMigrate = require $root . '/bootstrap/atak_sse_text_library_migration.php';
try {
    echo "Migration atak_sse_text_library (SSE — bibliothèque rédactionnelle)…\n";
    $atakSseTextLibraryMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_sse_text_library : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_sse_text_library', $e);
}
$migrationEnsurePdo();
