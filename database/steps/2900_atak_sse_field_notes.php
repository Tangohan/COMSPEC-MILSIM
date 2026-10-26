<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_sse_field_notes (SSE — fiches de renseignement simplifiées) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$atakSseFieldNotesMigrate = require $root . '/bootstrap/atak_sse_field_notes_migration.php';
try {
    echo "Migration atak_sse_field_notes (SSE — fiches de renseignement simplifiées)…\n";
    $atakSseFieldNotesMigrate($pdo, $sseCliLog);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_sse_field_notes : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_sse_field_notes', $e);
}
$migrationEnsurePdo();
