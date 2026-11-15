-- Retour arrière de migrations/2026_11_15_120_sse_wanted_notices.sql (étape sse_wanted_notices). À lancer à la main :
-- efface les avis de recherche, la chronologie propre aux identités (les autres traces, journal d'audit, contrôles, papiers,
-- restent intactes), les détections et les réglages de la notice. Les identités, papiers et fichiers SSE ne sont pas touchés.
DROP TABLE IF EXISTS sse_wanted_settings;
DROP TABLE IF EXISTS sse_detections;
DROP TABLE IF EXISTS sse_identity_events;
DROP TABLE IF EXISTS sse_wanted_notices;
