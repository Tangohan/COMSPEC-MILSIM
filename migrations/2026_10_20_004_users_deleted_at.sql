-- Registre des données / nettoyage des joueurs : users.deleted_at est requis pour repérer les comptes supprimés.
-- (Posé aussi automatiquement par App\Support\UsersDeletedAtSchema si la migration n'a pas tourné.)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'deleted_at');
SET @s := IF(@c = 0, 'ALTER TABLE users ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
UPDATE users SET deleted_at = COALESCE(updated_at, NOW()) WHERE email LIKE '%@deleted.invalid' AND deleted_at IS NULL;
