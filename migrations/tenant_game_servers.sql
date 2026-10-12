-- Serveurs de jeu (Arma 3) déclarés par une communauté : le téléphone ATAK prévient le joueur connecté à Athena
-- qui joue ailleurs que sur un serveur de sa communauté. Gestion › Mod ATAK › Serveurs de jeu.
-- tenant_game_server_presence : dernier serveur vu par joueur (une ligne par compte Steam et communauté).

CREATE TABLE IF NOT EXISTS `tenant_game_servers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `label` VARCHAR(120) NOT NULL,
  `ip_address` VARCHAR(64) NULL COMMENT 'Adresse publique IPv4 ou IPv6 du serveur',
  `server_name` VARCHAR(190) NULL COMMENT 'Nom affiché dans la liste des serveurs Arma (repli si l''IP est inconnue)',
  `port` INT UNSIGNED NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tgs_tenant` (`tenant_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tenant_game_server_presence` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `steam_uid` VARCHAR(32) NOT NULL,
  `player_name` VARCHAR(120) NULL,
  `server_ip` VARCHAR(64) NULL,
  `server_name` VARCHAR(190) NULL,
  `mission` VARCHAR(190) NULL,
  `world` VARCHAR(64) NULL,
  `matched_server_id` INT UNSIGNED NULL,
  `seen_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tgsp_player` (`tenant_id`, `steam_uid`),
  KEY `idx_tgsp_seen` (`tenant_id`, `seen_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
