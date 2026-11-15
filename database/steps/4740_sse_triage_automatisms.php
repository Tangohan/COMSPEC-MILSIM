<?php

declare(strict_types=1);

/**
 * Étape « SSE À trier : acquittements, règles Cerbère-Tri et déduplication des signaux » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente :
 *  - les tables sse_triage_state, sse_triage_rules et sse_triage_events ne sont créées que si elles manquent
 *    (migrations/2026_11_18_100_sse_triage_automatisms.sql) ; l'unicité (communauté, type, élément) est dans le CREATE TABLE ;
 *  - les colonnes dedup_key, last_seen_at, seen_count, changed_at, resolved_at, resolved_by et merged_into de sse_engine_signals
 *    ne sont ajoutées que si elles manquent, avec un index non unique (les doublons existants empêchent toute unicité tant que
 *    tools/sse-dedupe-signals.php --apply n'est pas passé).
 * Aucune donnée existante n'est modifiée. Sans cette étape le moteur déduplique quand même (clé calculée à partir du type, de la
 * règle, du dossier et du titre) et « À trier » reste lisible, sans acquittement persistant.
 * Retour arrière : migrations/rollback/sse_triage_automatisms_down.sql.
 */

$triageSchema = new \App\Support\Migrations\SchemaInspector($pdo);
$triageTables = ['sse_triage_state', 'sse_triage_rules', 'sse_triage_events'];
$triageMissing = array_values(array_filter($triageTables, static fn (string $t): bool => !$triageSchema->tableExists($t)));
if ($triageMissing === []) {
    echo "  [OK] Tables de tri déjà présentes.\n";
} else {
    $triageSchema->runSqlFile($root . '/migrations/2026_11_18_100_sse_triage_automatisms.sql');
    foreach ($triageTables as $triageTable) {
        if (!$triageSchema->tableExists($triageTable)) {
            throw new RuntimeException('Création de la table ' . $triageTable . ' impossible.');
        }
    }
    echo '  [OK] Tables créées : ' . implode(', ', $triageMissing) . ".\n";
}

if (!$triageSchema->tableExists('sse_engine_signals')) {
    echo "  [OK] Table sse_engine_signals absente : les colonnes seront ajoutées à la prochaine exécution de l'étape.\n";
} else {
    $triageColumns = [
        'dedup_key' => "VARCHAR(190) DEFAULT NULL COMMENT 'type|règle|dossier|variante : un seul signal ouvert par clé'",
        'last_seen_at' => "DATETIME DEFAULT NULL COMMENT 'dernière observation par le moteur'",
        'seen_count' => 'INT UNSIGNED NOT NULL DEFAULT 1',
        'changed_at' => "DATETIME DEFAULT NULL COMMENT 'dernier changement de contenu (titre, détail, gravité)'",
        'resolved_at' => 'DATETIME DEFAULT NULL',
        'resolved_by' => "VARCHAR(40) DEFAULT NULL COMMENT 'cerbere-detection, cerbere-dedoublonnage ou numéro de compte'",
        'merged_into' => 'INT UNSIGNED DEFAULT NULL',
    ];
    $triageAdded = [];
    foreach ($triageColumns as $triageColumn => $triageDefinition) {
        if ($triageSchema->addColumnIfMissing('sse_engine_signals', $triageColumn, $triageDefinition)) {
            $triageAdded[] = $triageColumn;
        }
    }
    if ($triageSchema->addIndexIfMissing('sse_engine_signals', 'idx_sse_signal_dedup', ['tenant_id', 'dedup_key', 'status'])) {
        $triageAdded[] = 'idx_sse_signal_dedup';
    }
    foreach (array_keys($triageColumns) as $triageColumn) {
        if (!$triageSchema->columnExists('sse_engine_signals', $triageColumn)) {
            throw new RuntimeException('Colonne sse_engine_signals.' . $triageColumn . ' impossible à créer.');
        }
    }
    echo $triageAdded === []
        ? "  [OK] Colonnes de déduplication déjà présentes.\n"
        : '  [OK] sse_engine_signals : ajouté ' . implode(', ', $triageAdded) . ".\n";
}
