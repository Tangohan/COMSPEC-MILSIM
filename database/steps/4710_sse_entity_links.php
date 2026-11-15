<?php

declare(strict_types=1);

/**
 * Étape « SSE liaisons : liens de provenance entre éléments » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : la table n'est créée que si elle manque
 * (CREATE TABLE IF NOT EXISTS, depuis migrations/2026_11_16_110_sse_entity_links.sql). Aucune donnée n'est ajoutée ni
 * modifiée : sans cette étape, le widget « Liaisons » déduit les liens des colonnes existantes (mode dégradé). Les liens
 * déductibles des colonnes se rétro-remplissent à part, à la main : php tools/sse-backfill-links.php (--apply).
 * Retour arrière : migrations/rollback/sse_entity_links_down.sql.
 */

$sseLinksSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if ($sseLinksSchema->tableExists('sse_entity_links')) {
    echo "  [OK] Table des liens de provenance déjà présente.\n";
} else {
    $sseLinksSchema->runSqlFile($root . '/migrations/2026_11_16_110_sse_entity_links.sql');
    if (!$sseLinksSchema->tableExists('sse_entity_links')) {
        throw new RuntimeException('Création de la table sse_entity_links impossible.');
    }
    echo "  [OK] Table créée : sse_entity_links.\n";
}
