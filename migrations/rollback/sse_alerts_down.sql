-- Retour arrière de migrations/2026_11_20_100_sse_alerts.sql (étape sse_alerts). À lancer à la main : efface les adresses de
-- signalement, les services à informer notés, les levées de fiches de vigilance, les signalements, les propositions, les accusés
-- de lecture et les emblèmes téléversés des services (les fichiers image restent sur le disque). Les avis, les fiches émises et
-- les réglages ne sont pas touchés.
DROP TABLE IF EXISTS sse_alert_acks;
DROP TABLE IF EXISTS sse_alert_reports;
DROP TABLE IF EXISTS sse_alert_proposals;
DROP TABLE IF EXISTS sse_alert_links;
DROP TABLE IF EXISTS sse_service_emblems;
