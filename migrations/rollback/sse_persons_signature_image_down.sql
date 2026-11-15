-- Retour arrière de migrations/2026_11_15_110_sse_persons_signature_image.sql (étape sse_persons_signature_image). À lancer à
-- la main : retire seulement le chemin de la signature tracée au SEEK II. Les fichiers PNG de uploads/sse-id-signatures restent
-- sur le disque ; les signatures d'indicatif (signed_by_callsign...) ne changent pas.
ALTER TABLE sse_persons DROP COLUMN signature_image;
