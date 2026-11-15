-- Retour arrière des migrations 2026_11_17_110_sse_persons_work.sql et 2026_11_17_111_sse_person_views.sql (étape sse_persons_work).
-- À lancer à la main : efface la classification, les verrouillages et les clôtures posés sur les fiches personnes, et les dates
-- de dernière ouverture. Les fiches elles-mêmes, le journal d'audit SSE et le réglage de verrouillage automatique ne sont pas touchés.
DROP TABLE IF EXISTS sse_person_views;
ALTER TABLE sse_persons DROP COLUMN close_reason;
ALTER TABLE sse_persons DROP COLUMN closed_by;
ALTER TABLE sse_persons DROP COLUMN closed_at;
ALTER TABLE sse_persons DROP COLUMN lock_until;
ALTER TABLE sse_persons DROP COLUMN lock_reason;
ALTER TABLE sse_persons DROP COLUMN locked_by;
ALTER TABLE sse_persons DROP COLUMN locked_at;
ALTER TABLE sse_persons DROP COLUMN classification;
