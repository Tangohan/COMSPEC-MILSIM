<?php

declare(strict_types=1);

/**
 * Étape « Campagnes de fiabilisation : colonnes d'ouverture automatique » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : les colonnes auto_created, auto_year et
 * forced_on et l'unicité (communauté, année) ne sont ajoutées que si elles manquent. Elles corrigent les bases où la table
 * data_campaigns a été créée avant leur ajout (l'étape data_campaigns ne modifie jamais une table déjà présente).
 * Aucune donnée modifiée. Retour arrière : migrations/rollback/data_campaigns_auto_columns_down.sql.
 */

$campaignSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if (!$campaignSchema->tableExists('data_campaigns')) {
    echo "  [OK] Table data_campaigns absente : les colonnes seront créées avec elle.\n";
} else {
    $added = [];
    if ($campaignSchema->addColumnIfMissing('data_campaigns', 'auto_created', "TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'ouverte par Athena'")) {
        $added[] = 'auto_created';
    }
    if ($campaignSchema->addColumnIfMissing('data_campaigns', 'auto_year', "SMALLINT UNSIGNED DEFAULT NULL COMMENT 'année de la campagne ouverte par Athena : une seule par communauté et par année'")) {
        $added[] = 'auto_year';
    }
    if ($campaignSchema->addColumnIfMissing('data_campaigns', 'forced_on', "DATE DEFAULT NULL COMMENT 'jour où l''ouverture a été forcée avant la période automatique'")) {
        $added[] = 'forced_on';
    }
    if (!$campaignSchema->indexExists('data_campaigns', 'uq_data_campaign_auto') && $campaignSchema->columnExists('data_campaigns', 'auto_year')) {
        $pdo->exec('ALTER TABLE `data_campaigns` ADD UNIQUE KEY `uq_data_campaign_auto` (`tenant_id`, `auto_year`)');
        $added[] = 'uq_data_campaign_auto';
    }
    foreach (['auto_created', 'auto_year', 'forced_on'] as $column) {
        if (!$campaignSchema->columnExists('data_campaigns', $column)) {
            throw new RuntimeException('Colonne data_campaigns.' . $column . ' impossible à créer.');
        }
    }
    echo $added === []
        ? "  [OK] Colonnes d'ouverture automatique déjà présentes.\n"
        : '  [OK] Ajouté : ' . implode(', ', $added) . ".\n";
}
