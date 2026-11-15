<?php

declare(strict_types=1);

/**
 * Étape « SSE identités : avis de recherche, chronologie, détections » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : chaque table n'est créée que si elle manque
 * (CREATE TABLE IF NOT EXISTS, depuis migrations/2026_11_15_120_sse_wanted_notices.sql). Aucune donnée n'est ajoutée ni
 * modifiée : sans avis, les fiches identité s'affichent comme avant. Retour arrière :
 * migrations/rollback/sse_wanted_notices_down.sql.
 */

$sseWantedSchema = new \App\Support\Migrations\SchemaInspector($pdo);
$sseWantedTables = ['sse_wanted_notices', 'sse_identity_events', 'sse_detections', 'sse_wanted_settings'];
$sseWantedMissing = array_values(array_filter($sseWantedTables, static fn (string $t): bool => !$sseWantedSchema->tableExists($t)));
if ($sseWantedMissing === []) {
    echo "  [OK] Tables des avis de recherche déjà présentes.\n";
} else {
    $sseWantedSchema->runSqlFile($root . '/migrations/2026_11_15_120_sse_wanted_notices.sql');
    foreach ($sseWantedTables as $sseWantedTable) {
        if (!$sseWantedSchema->tableExists($sseWantedTable)) {
            throw new RuntimeException('Création de la table ' . $sseWantedTable . ' impossible.');
        }
    }
    echo '  [OK] Tables créées : ' . implode(', ', $sseWantedMissing) . ".\n";
}
