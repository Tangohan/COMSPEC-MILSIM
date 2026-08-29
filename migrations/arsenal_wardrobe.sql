-- ACE Arsenal wardrobes + collections d’équipement (Athena sync)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `arsenal_equipment_collections` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `owner_user_id` int unsigned DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `slug` varchar(140) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `visibility` enum('personal','unit','tenant') NOT NULL DEFAULT 'personal',
  `tags_json` json DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_arsenal_coll_tenant_slug` (`tenant_id`,`slug`),
  KEY `idx_arsenal_coll_owner` (`tenant_id`,`owner_user_id`),
  KEY `idx_arsenal_coll_visibility` (`tenant_id`,`visibility`),
  CONSTRAINT `fk_arsenal_coll_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_arsenal_coll_owner` FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `arsenal_wardrobes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `steam_uid` varchar(32) DEFAULT NULL,
  `collection_id` bigint unsigned DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `slug` varchar(140) NOT NULL,
  `source` varchar(40) NOT NULL DEFAULT 'ace_arsenal',
  `payload_format` varchar(24) NOT NULL DEFAULT 'arma_loadout_str',
  `payload_text` mediumtext NOT NULL,
  `payload_sha256` char(64) NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `is_favorite` tinyint(1) NOT NULL DEFAULT 0,
  `last_synced_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_arsenal_wardrobe_user_slug` (`tenant_id`,`user_id`,`slug`),
  KEY `idx_arsenal_wardrobe_steam` (`tenant_id`,`steam_uid`),
  KEY `idx_arsenal_wardrobe_collection` (`collection_id`),
  KEY `idx_arsenal_wardrobe_updated` (`tenant_id`,`user_id`,`updated_at`),
  CONSTRAINT `fk_arsenal_wardrobe_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_arsenal_wardrobe_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_arsenal_wardrobe_collection` FOREIGN KEY (`collection_id`) REFERENCES `arsenal_equipment_collections` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `arsenal_collection_wardrobes` (
  `collection_id` bigint unsigned NOT NULL,
  `wardrobe_id` bigint unsigned NOT NULL,
  `sort_order` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`collection_id`,`wardrobe_id`),
  KEY `idx_arsenal_cw_wardrobe` (`wardrobe_id`),
  CONSTRAINT `fk_arsenal_cw_collection` FOREIGN KEY (`collection_id`) REFERENCES `arsenal_equipment_collections` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_arsenal_cw_wardrobe` FOREIGN KEY (`wardrobe_id`) REFERENCES `arsenal_wardrobes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
