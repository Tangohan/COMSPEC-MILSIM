-- Page publique du joueur : bannière personnelle en fond de l’en-tête (choisie dans Mon compte > Ma page publique).
-- Chemin d’une image ré-encodée par le site sous uploads/public-cv/ ; NULL = bannière de la communauté, sinon trame.
-- (Posée aussi automatiquement au premier envoi par UserPublicCvRepository::ensureBannerColumn.)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_public_cv' AND COLUMN_NAME = 'banner_path');
SET @s := IF(@c = 0, 'ALTER TABLE user_public_cv ADD COLUMN banner_path VARCHAR(255) NULL DEFAULT NULL', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
