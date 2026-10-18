-- Équipement remonté par le mod (profil opérateur) : catalogue d’objets observés, usage par joueur et
-- par jour, mods détectés, et kits par rôle publiés comme tenues d’arsenal.
-- Alimenté par POST /api/atak/operator/register|sync (App\Services\GameEquipment\GameItemUsageRecorder).
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `game_item_catalog` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `class_name` varchar(128) NOT NULL,
  `name` varchar(160) NOT NULL DEFAULT '',
  `kind` varchar(24) NOT NULL DEFAULT 'item',
  `mod_name` varchar(160) NOT NULL DEFAULT '',
  `max_ammo` smallint unsigned DEFAULT NULL,
  `first_seen` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_seen` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_game_item_catalog_class` (`tenant_id`,`class_name`),
  KEY `idx_game_item_catalog_kind` (`tenant_id`,`kind`),
  CONSTRAINT `fk_game_item_catalog_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `game_item_usage` (
  `tenant_id` int unsigned NOT NULL,
  `steam_id` varchar(32) NOT NULL,
  `class_name` varchar(128) NOT NULL,
  `kind` varchar(24) NOT NULL DEFAULT 'item',
  `usage_day` date NOT NULL,
  `hits` int unsigned NOT NULL DEFAULT 1,
  `last_seen` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tenant_id`,`steam_id`,`class_name`,`usage_day`),
  KEY `idx_game_item_usage_day` (`tenant_id`,`usage_day`),
  KEY `idx_game_item_usage_class` (`tenant_id`,`class_name`,`usage_day`),
  CONSTRAINT `fk_game_item_usage_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `game_detected_mods` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `mod_dir` varchar(160) NOT NULL,
  `name` varchar(160) NOT NULL DEFAULT '',
  `workshop_id` varchar(24) NOT NULL DEFAULT '',
  `is_dlc` tinyint(1) NOT NULL DEFAULT 0,
  `first_seen` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_seen` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_game_detected_mods_dir` (`tenant_id`,`mod_dir`),
  CONSTRAINT `fk_game_detected_mods_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `game_player_mods` (
  `tenant_id` int unsigned NOT NULL,
  `steam_id` varchar(32) NOT NULL,
  `mod_dir` varchar(160) NOT NULL,
  `last_seen` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tenant_id`,`steam_id`,`mod_dir`),
  KEY `idx_game_player_mods_dir` (`tenant_id`,`mod_dir`,`last_seen`),
  CONSTRAINT `fk_game_player_mods_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `game_role_kits` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `job_role_id` int unsigned DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `description` varchar(1000) DEFAULT NULL,
  `slots_json` json NOT NULL,
  `payload_text` mediumtext NOT NULL,
  `required_mods_json` json DEFAULT NULL,
  `wardrobe_id` bigint unsigned DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_game_role_kits_role` (`tenant_id`,`job_role_id`),
  KEY `idx_game_role_kits_wardrobe` (`wardrobe_id`),
  CONSTRAINT `fk_game_role_kits_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
