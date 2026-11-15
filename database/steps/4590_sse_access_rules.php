<?php

declare(strict_types=1);

/**
 * Étape « Droits d'accès du portail SSE » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : chaque table n'est créée que si elle
 * manque (CREATE TABLE IF NOT EXISTS, depuis migrations/2026_11_15_100_sse_access_rules.sql). Aucune règle n'est
 * ajoutée : sans règle, le portail SSE se comporte exactement comme avant. Retour arrière :
 * migrations/rollback/sse_access_rules_down.sql.
 */

$sseAccessSchema = new \App\Support\Migrations\SchemaInspector($pdo);
$sseAccessTables = ['sse_access_rules', 'sse_access_rule_history'];
$sseAccessMissing = array_values(array_filter($sseAccessTables, static fn (string $t): bool => !$sseAccessSchema->tableExists($t)));
if ($sseAccessMissing === []) {
    echo "  [OK] Tables des droits d'accès SSE déjà présentes.\n";
} else {
    $sseAccessSchema->runSqlFile($root . '/migrations/2026_11_15_100_sse_access_rules.sql');
    foreach ($sseAccessTables as $sseAccessTable) {
        if (!$sseAccessSchema->tableExists($sseAccessTable)) {
            throw new RuntimeException('Création de la table ' . $sseAccessTable . ' impossible.');
        }
    }
    echo '  [OK] Tables créées : ' . implode(', ', $sseAccessMissing) . ".\n";
}
