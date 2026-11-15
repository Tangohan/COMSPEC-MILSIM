<?php

declare(strict_types=1);

/**
 * Étape « SSE avis de recherche : service demandeur, renouvellement et validation » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : la table n'est créée que si elle manque
 * (CREATE TABLE IF NOT EXISTS, depuis migrations/2026_11_18_100_sse_wanted_process.sql). Aucune colonne ajoutée, aucune donnée
 * modifiée : sans cette table, l'avis se place avec le texte de son service seul. Retour arrière :
 * migrations/rollback/sse_wanted_process_down.sql.
 */

$sseProcessSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if (!$sseProcessSchema->tableExists('sse_wanted_notices')) {
    echo "  [OK] Table sse_wanted_notices absente : l'étape des avis de recherche passe d'abord.\n";
} elseif ($sseProcessSchema->tableExists('sse_wanted_notice_meta')) {
    echo "  [OK] Table sse_wanted_notice_meta déjà présente.\n";
} else {
    $sseProcessSchema->runSqlFile($root . '/migrations/2026_11_18_100_sse_wanted_process.sql');
    if (!$sseProcessSchema->tableExists('sse_wanted_notice_meta')) {
        throw new RuntimeException('Création de la table sse_wanted_notice_meta impossible.');
    }
    echo "  [OK] Table créée : sse_wanted_notice_meta.\n";
}
