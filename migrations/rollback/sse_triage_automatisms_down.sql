-- Retour arrière de l'étape sse_triage_automatisms (migrations/2026_11_18_100_sse_triage_automatisms.sql et colonnes de
-- sse_engine_signals). À lancer à la main : efface les acquittements, mises en veille, assignations, règles Cerbère-Tri et leur
-- journal, puis retire les colonnes de déduplication des signaux. Les signaux eux-mêmes restent ; ceux qui ont le statut merged,
-- resolved ou dormant gardent ce statut (la page Anomalies ne montre que les « open »).
DROP TABLE IF EXISTS sse_triage_events;
DROP TABLE IF EXISTS sse_triage_rules;
DROP TABLE IF EXISTS sse_triage_state;
ALTER TABLE sse_engine_signals DROP INDEX idx_sse_signal_dedup;
ALTER TABLE sse_engine_signals DROP COLUMN merged_into, DROP COLUMN resolved_by, DROP COLUMN resolved_at, DROP COLUMN changed_at, DROP COLUMN seen_count, DROP COLUMN last_seen_at, DROP COLUMN dedup_key;
