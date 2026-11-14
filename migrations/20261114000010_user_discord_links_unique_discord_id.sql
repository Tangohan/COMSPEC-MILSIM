-- Un compte Discord ne sert qu'à un seul compte du site : la connexion « Continuer avec Discord » retrouve le compte
-- par discord_user_id, qui doit donc être unique.
-- Avant d'ajouter la contrainte, les doublons éventuels sont retirés : pour chaque discord_user_id on garde la liaison
-- la plus récente (linked_at le plus grand, à égalité le user_id le plus grand) ; les autres liaisons sont supprimées
-- (le membre concerné peut relier de nouveau son Discord depuis Mon compte).
SET NAMES utf8mb4;

DELETE older
FROM user_discord_links older
JOIN user_discord_links newer
  ON newer.discord_user_id = older.discord_user_id
 AND (newer.linked_at > older.linked_at
      OR (newer.linked_at = older.linked_at AND newer.user_id > older.user_id));

ALTER TABLE user_discord_links
    DROP INDEX idx_user_discord_links_discord,
    ADD UNIQUE KEY uq_user_discord_links_discord (discord_user_id);
