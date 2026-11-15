-- Retour arrière de migrations/2026_11_15_120_sse_seek_records_capture_json.sql (étape sse_seek_capture_notes). À lancer à la
-- main : retire seulement les motifs de capture des fiches SEEK II ; les fiches, qualités et signatures ne changent pas.
ALTER TABLE sse_seek_records DROP COLUMN capture_json;
