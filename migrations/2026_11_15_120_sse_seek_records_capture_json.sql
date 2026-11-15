-- SEEK II : motifs de capture par modalité (empreintes, iris) envoyés avec la fiche seek_record (champ facultatif « capture »).
-- JSON borné par App\Support\SseCaptureNotes : qualité, détail par doigt ou par œil, motifs lisibles. Colonne vide = rien à signaler.
-- Retour arrière : migrations/rollback/sse_seek_capture_notes_down.sql.
ALTER TABLE sse_seek_records ADD COLUMN capture_json TEXT NULL;
