-- Retour arrière de migrations/2026_11_15_080_photo_library.sql (étape photo_library). À lancer à la main : efface les
-- visages étiquetés, zones masquées, commentaires, étiquettes et journal des versements de la photothèque. Les photos
-- (recon_images) ne sont pas touchées.
-- ATTENTION : si des masques sont posés, retirez-les d'abord depuis la photothèque (photo par photo) : le fichier servi
-- est alors la version masquée, l'original est gardé dans storage/private/photo-originals et n'est rendu qu'au retrait.
DROP TABLE IF EXISTS photo_library_sse;
DROP TABLE IF EXISTS photo_library_state;
DROP TABLE IF EXISTS photo_library_labels;
DROP TABLE IF EXISTS photo_library_comments;
DROP TABLE IF EXISTS photo_library_masks;
DROP TABLE IF EXISTS photo_library_tags;
-- La colonne ajoutée à recon_images (compte de l'auteur) peut rester ; pour la retirer :
-- ALTER TABLE recon_images DROP COLUMN author_user_id;
