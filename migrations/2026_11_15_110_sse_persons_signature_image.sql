-- SEEK II : signature tracée au pad du terminal. Le mod envoie des traits, le site rend le PNG (SseSignatureStrokes) et
-- garde ici son chemin relatif à public/ (uploads/sse-id-signatures/...). Colonne vide = pas de signature tracée.
-- Retour arrière : migrations/rollback/sse_persons_signature_image_down.sql.
ALTER TABLE sse_persons ADD COLUMN signature_image VARCHAR(255) DEFAULT NULL;
