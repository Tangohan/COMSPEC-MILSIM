<?php

declare(strict_types=1);

/**
 * Étape « SSE fiches PDF : documents émis, brouillons, trames de phrases » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : chaque table n'est créée que si elle manque
 * (CREATE TABLE IF NOT EXISTS, depuis migrations/2026_11_16_100_sse_documents.sql). Aucune donnée n'est ajoutée ni modifiée :
 * sans cette étape, les fiches s'affichent comme avant et seul l'avis de recherche reste émettable. Retour arrière :
 * migrations/rollback/sse_documents_down.sql.
 */

$sseDocSchema = new \App\Support\Migrations\SchemaInspector($pdo);
$sseDocTables = ['sse_doc_issues', 'sse_doc_drafts', 'sse_doc_phrases'];
$sseDocMissing = array_values(array_filter($sseDocTables, static fn (string $t): bool => !$sseDocSchema->tableExists($t)));
if ($sseDocMissing === []) {
    echo "  [OK] Tables des fiches PDF déjà présentes.\n";
} else {
    $sseDocSchema->runSqlFile($root . '/migrations/2026_11_16_100_sse_documents.sql');
    foreach ($sseDocTables as $sseDocTable) {
        if (!$sseDocSchema->tableExists($sseDocTable)) {
            throw new RuntimeException('Création de la table ' . $sseDocTable . ' impossible.');
        }
    }
    echo '  [OK] Tables créées : ' . implode(', ', $sseDocMissing) . ".\n";
}
