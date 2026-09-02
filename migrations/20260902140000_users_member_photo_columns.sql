-- Photo de compte et portrait opérateur pour les communautés déjà en place.
-- Idempotent via bootstrap/users_member_photo_columns_migration.php (vérifie information_schema, n’invente pas de photo).
-- La colonne users.avatar_path n’existe pas et ne doit pas être créée.

-- users.avatar_url : photo de compte (NULL si aucune photo)
-- personnel_profiles.character_portrait_path : portrait opérateur (NULL si aucun portrait)
