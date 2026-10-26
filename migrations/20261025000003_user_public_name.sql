-- Pseudo public : le nom qu'un membre choisit pour les pages publiques du site (liste des contributeurs,
-- propositions de traduction) à la place de son indicatif. Modifiable dans Mon compte > Préférences et sur /contribuer.
-- (Posé aussi automatiquement par App\Support\UserPublicName si la migration n'a pas tourné.)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'public_name');
SET @s := IF(@c = 0, 'ALTER TABLE users ADD COLUMN public_name VARCHAR(40) NULL DEFAULT NULL COMMENT ''Pseudo public (pages publiques)'' AFTER callsign', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
