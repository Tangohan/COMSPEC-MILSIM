<?php

declare(strict_types=1);

/**
 * Étape « SSE éditeur de documents : brouillon automatique côté serveur » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : la table n'est créée que si elle manque
 * (CREATE TABLE IF NOT EXISTS, depuis migrations/2026_11_24_100_sse_document_drafts.sql, unicité dans le CREATE TABLE).
 * Aucune colonne ajoutée, aucune donnée modifiée : les documents existants (texte historique) restent lus tels quels.
 * Sans cette table, l'éditeur garde seulement le brouillon du navigateur.
 * Retour arrière : migrations/rollback/sse_document_drafts_down.sql.
 */

$sseDocDraftSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if ($sseDocDraftSchema->tableExists('sse_document_drafts')) {
    echo "  [OK] sse_document_drafts déjà présente.\n";
} else {
    $sseDocDraftSchema->runSqlFile($root . '/migrations/2026_11_24_100_sse_document_drafts.sql');
    if (!$sseDocDraftSchema->tableExists('sse_document_drafts')) {
        throw new RuntimeException('Création de la table sse_document_drafts impossible.');
    }
    echo "  [OK] Table sse_document_drafts créée.\n";
}
