-- SEEK II : signature du contrôleur tracée au pad, envoyée avec la fiche seek_record. Le site dessine le PNG
-- (SseSignatureStrokes) et garde ici son chemin relatif à public/. Colonne vide = pas de signature tracée.
-- Retour arrière : migrations/rollback/sse_persons_signature_image_down.sql.
ALTER TABLE sse_seek_records ADD COLUMN signature_image VARCHAR(255) DEFAULT NULL;
