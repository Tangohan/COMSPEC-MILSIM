-- Comptes Discord reliés aux comptes du site pour les « rôles liés » de Discord (Linked Roles).
-- Le membre relie son compte depuis Discord (menu Rôles liés du serveur) ou depuis Mon compte › Préférences :
-- le site renvoie à Discord « compte Athena », « membre d'une communauté », le nombre de communautés, l'ancienneté
-- et le nom de ses communautés (affiché sur son profil Discord). Le serveur Discord donne ensuite les rôles dont
-- les conditions sont remplies. Jetons OAuth chiffrés (App\Support\SecretCipher), jamais affichés.
-- App\Repositories\UserDiscordLinkRepository, App\Services\Integrations\DiscordLinkedRoles.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `user_discord_links` (
  `user_id` int unsigned NOT NULL,
  `discord_user_id` varchar(24) NOT NULL,
  `discord_username` varchar(64) NOT NULL DEFAULT '',
  `access_token` text NOT NULL COMMENT 'Chiffré (sc1:)',
  `refresh_token` text NOT NULL COMMENT 'Chiffré (sc1:)',
  `expires_at` datetime NOT NULL,
  `linked_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `synced_at` datetime DEFAULT NULL,
  `last_error` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`user_id`),
  KEY `idx_user_discord_links_discord` (`discord_user_id`),
  KEY `idx_user_discord_links_synced` (`synced_at`),
  CONSTRAINT `user_discord_links_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
