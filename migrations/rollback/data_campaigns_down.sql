-- Retour arrière de migrations/2026_11_15_090_data_campaigns.sql (étape data_campaigns). À lancer à la main : efface les
-- campagnes de fiabilisation, les réponses des membres, leurs instantanés, les signalements et le journal des rappels.
-- Les fiches des membres et les demandes de correction (personnel_correction_requests) ne sont pas touchées.
DROP TABLE IF EXISTS data_campaign_reminders;
DROP TABLE IF EXISTS data_campaign_flags;
DROP TABLE IF EXISTS data_campaign_members;
DROP TABLE IF EXISTS data_campaigns;
DROP TABLE IF EXISTS data_campaign_settings;
