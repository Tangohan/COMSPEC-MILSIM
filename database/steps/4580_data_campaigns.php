<?php

declare(strict_types=1);

/**
 * Étape « Campagne de fiabilisation des données : campagnes, réponses des membres, signalements, rappels » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : chaque table n'est créée que si elle
 * manque (CREATE TABLE IF NOT EXISTS, depuis migrations/2026_11_15_090_data_campaigns.sql). Aucune donnée n'est ajoutée ni
 * modifiée : sans campagne ouverte, rien ne change pour les membres, et la tâche planifiée reste sans effet tant que
 * la communauté n'a pas activé l'ouverture automatique. Retour arrière : migrations/rollback/data_campaigns_down.sql.
 */

$dataCampaignSchema = new \App\Support\Migrations\SchemaInspector($pdo);
$dataCampaignTables = ['data_campaign_settings', 'data_campaigns', 'data_campaign_members', 'data_campaign_flags', 'data_campaign_reminders'];
$dataCampaignMissing = array_values(array_filter($dataCampaignTables, static fn (string $t): bool => !$dataCampaignSchema->tableExists($t)));
if ($dataCampaignMissing === []) {
    echo "  [OK] Tables de la campagne de fiabilisation déjà présentes.\n";
} else {
    $dataCampaignSchema->runSqlFile($root . '/migrations/2026_11_15_090_data_campaigns.sql');
    foreach ($dataCampaignTables as $dataCampaignTable) {
        if (!$dataCampaignSchema->tableExists($dataCampaignTable)) {
            throw new RuntimeException('Création de la table ' . $dataCampaignTable . ' impossible.');
        }
    }
    echo '  [OK] Tables créées : ' . implode(', ', $dataCampaignMissing) . ".\n";
}
