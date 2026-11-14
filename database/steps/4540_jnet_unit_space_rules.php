<?php

declare(strict_types=1);

/**
 * Étape « JNET : réglages par espace d'unité, accès par unité, gestionnaires, relais automatiques » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : les trois tables ne sont créées que si
 * elles manquent (CREATE TABLE IF NOT EXISTS, depuis migrations/2026_11_14_060_jnet_unit_space_rules.sql) et les colonnes
 * require_ack, auto_relay et post_min de jnet_unit_spaces ne sont ajoutées que si elles manquent. Aucune donnée n'est
 * modifiée : par défaut, rien ne change pour les espaces existants. Retour arrière :
 * migrations/rollback/jnet_unit_space_rules_down.sql.
 */

$jnetRulesSchema = new \App\Support\Migrations\SchemaInspector($pdo);
$jnetRulesTables = ['jnet_unit_space_access', 'jnet_unit_space_managers', 'jnet_unit_auto_relays'];
$jnetRulesMissing = array_values(array_filter($jnetRulesTables, static fn (string $t): bool => !$jnetRulesSchema->tableExists($t)));
if ($jnetRulesMissing === []) {
    echo "  [OK] Tables des réglages d'espace JNET déjà présentes.\n";
} else {
    $jnetRulesSchema->runSqlFile($root . '/migrations/2026_11_14_060_jnet_unit_space_rules.sql');
    foreach ($jnetRulesTables as $jnetRulesTable) {
        if (!$jnetRulesSchema->tableExists($jnetRulesTable)) {
            throw new RuntimeException('Création de la table ' . $jnetRulesTable . ' impossible.');
        }
    }
    echo '  [OK] Tables créées : ' . implode(', ', $jnetRulesMissing) . ".\n";
}
if ($jnetRulesSchema->tableExists('jnet_unit_spaces')) {
    $jnetRulesColumns = [
        'require_ack' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'auto_relay' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'post_min' => "VARCHAR(8) NOT NULL DEFAULT 'member'",
    ];
    foreach ($jnetRulesColumns as $jnetRulesColumn => $jnetRulesDefinition) {
        if ($jnetRulesSchema->addColumnIfMissing('jnet_unit_spaces', $jnetRulesColumn, $jnetRulesDefinition)) {
            echo '  [OK] Colonne jnet_unit_spaces.' . $jnetRulesColumn . " ajoutée.\n";
        } else {
            echo '  [OK] Colonne jnet_unit_spaces.' . $jnetRulesColumn . " déjà présente.\n";
        }
    }
}
