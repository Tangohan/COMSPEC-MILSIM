-- Retour arrière de l'étape data_campaigns_auto_columns (colonnes d'ouverture automatique des campagnes).
-- À n'exécuter que si aucune campagne n'a été ouverte automatiquement.
ALTER TABLE data_campaigns DROP INDEX uq_data_campaign_auto;
ALTER TABLE data_campaigns DROP COLUMN forced_on, DROP COLUMN auto_year, DROP COLUMN auto_created;
