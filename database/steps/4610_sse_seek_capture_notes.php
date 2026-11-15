<?php

declare(strict_types=1);

/**
 * Étape « SEEK II : motifs de capture » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : la colonne sse_seek_records.capture_json
 * n'est ajoutée que si elle manque (depuis migrations/2026_11_15_120_sse_seek_records_capture_json.sql). Aucune donnée modifiée.
 * Retour arrière : migrations/rollback/sse_seek_capture_notes_down.sql.
 */

$seekCaptureSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if (!$seekCaptureSchema->tableExists('sse_seek_records')) {
    echo "  [OK] Table sse_seek_records absente : la colonne sera créée avec elle (sse_file_queries).\n";
} elseif ($seekCaptureSchema->columnExists('sse_seek_records', 'capture_json')) {
    echo "  [OK] Colonne sse_seek_records.capture_json déjà présente.\n";
} else {
    $seekCaptureSchema->runSqlFile($root . '/migrations/2026_11_15_120_sse_seek_records_capture_json.sql');
    if (!$seekCaptureSchema->columnExists('sse_seek_records', 'capture_json')) {
        throw new RuntimeException('Création de la colonne sse_seek_records.capture_json impossible.');
    }
    echo "  [OK] Colonne créée : sse_seek_records.capture_json.\n";
}
