<?php

declare(strict_types=1);

/**
 * Étape « SSE détections Cerbère-Lecture : réglages des règles et décisions sur les propositions » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : les tables sse_detection_rule_settings et
 * sse_detection_decisions ne sont créées que si elles manquent (CREATE TABLE IF NOT EXISTS, depuis
 * migrations/2026_11_23_100_sse_detection_rules.sql) ; les unicités sont dans les CREATE TABLE. Aucune colonne ajoutée,
 * aucune donnée modifiée, aucun droit créé (la page « Détections Cerbère » suit les droits de la gestion des fiches).
 * Sans ces tables : le lexique livré s'applique, les propositions restent applicables, « Ignorer » et « Mauvaise détection »
 * ne sont pas gardés et la page de gestion est en lecture seule. Compatible SQLite et MySQL.
 * Rétro-traitement à part, jamais automatique : php tools/sse-redetect.php (simulation par défaut, --apply pour écrire).
 * Retour arrière : migrations/rollback/sse_detection_rules_down.sql.
 */

$detectionSchema = new \App\Support\Migrations\SchemaInspector($pdo);
$detectionTables = ['sse_detection_rule_settings', 'sse_detection_decisions'];
$detectionMissing = array_values(array_filter($detectionTables, static fn (string $t): bool => !$detectionSchema->tableExists($t)));
if ($detectionMissing === []) {
    echo "  [OK] Tables des détections déjà présentes.\n";
} else {
    $detectionSchema->runSqlFile($root . '/migrations/2026_11_23_100_sse_detection_rules.sql');
    foreach ($detectionTables as $detectionTable) {
        if (!$detectionSchema->tableExists($detectionTable)) {
            throw new RuntimeException('Création de la table ' . $detectionTable . ' impossible.');
        }
    }
    echo '  [OK] Tables créées : ' . implode(', ', $detectionMissing) . ".\n";
}
