<?php

declare(strict_types=1);

/**
 * Étape « SSE éditeur de documents : thèmes, références, signatures » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : colonne et tables ajoutées seulement si elles
 * manquent (CREATE TABLE IF NOT EXISTS depuis migrations/2026_11_26_100_sse_document_pro.sql). Aucune donnée modifiée.
 * Retour arrière : migrations/rollback/sse_document_pro_down.sql.
 */

$sseProSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if ($sseProSchema->tableExists('sse_documents')) {
    if ($sseProSchema->addColumnIfMissing('sse_documents', 'sheet_theme', "VARCHAR(24) NOT NULL DEFAULT 'classique'")) {
        echo "  [OK] sse_documents.sheet_theme ajoutée.\n";
    } else {
        echo "  [OK] sse_documents.sheet_theme déjà présente.\n";
    }
}
$sseProSchema->runSqlFile($root . '/migrations/2026_11_26_100_sse_document_pro.sql');
foreach (['sse_document_counters', 'sse_document_signatures'] as $sseProTable) {
    if (!$sseProSchema->tableExists($sseProTable)) {
        throw new RuntimeException('Création de la table ' . $sseProTable . ' impossible.');
    }
    echo '  [OK] ' . $sseProTable . " présente.\n";
}
