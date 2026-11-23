<?php

declare(strict_types=1);

/**
 * Étape « SSE modèles de documents personnels » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : la table n'est créée que si elle manque
 * (CREATE TABLE IF NOT EXISTS, depuis migrations/2026_11_25_100_sse_document_templates.sql).
 * Aucune colonne ajoutée, aucune donnée modifiée.
 * Sans cette table, la galerie ne propose que les modèles du catalogue.
 * Retour arrière : migrations/rollback/sse_document_templates_down.sql.
 */

$sseDocTplSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if ($sseDocTplSchema->tableExists('sse_document_templates')) {
    echo "  [OK] sse_document_templates déjà présente.\n";
} else {
    $sseDocTplSchema->runSqlFile($root . '/migrations/2026_11_25_100_sse_document_templates.sql');
    if (!$sseDocTplSchema->tableExists('sse_document_templates')) {
        throw new RuntimeException('Création de la table sse_document_templates impossible.');
    }
    echo "  [OK] Table sse_document_templates créée.\n";
}
