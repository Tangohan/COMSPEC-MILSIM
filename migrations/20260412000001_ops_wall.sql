-- Mur opérationnel (socle unique) : pilotage + diffusion ciblée
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `ops_board_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `unit_id` int unsigned DEFAULT NULL,
  `block_type` enum('permanence_speciale','info_pratique','manifestation','flash_info') NOT NULL,
  `title` varchar(255) NOT NULL,
  `summary` text DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `visibility_level` varchar(64) NOT NULL DEFAULT 'tenant',
  `linked_type` enum('event','mission','formation','none') DEFAULT 'none',
  `linked_id` int unsigned DEFAULT NULL,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `publish_at` datetime DEFAULT NULL,
  `priority` enum('low','normal','high','critical') NOT NULL DEFAULT 'normal',
  `status` enum('draft','published','archived','expired') NOT NULL DEFAULT 'draft',
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
  `display_order` int NOT NULL DEFAULT 0,
  `created_by` int unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ops_board_items_tenant_block` (`tenant_id`,`block_type`,`status`),
  KEY `idx_ops_board_items_dates` (`start_date`,`end_date`,`publish_at`),
  KEY `idx_ops_board_items_priority` (`priority`,`is_pinned`,`display_order`),
  KEY `idx_ops_board_items_visibility` (`visibility_level`),
  CONSTRAINT `fk_ops_board_items_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ops_board_items_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ops_board_items_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ops_board_assignments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `item_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `role_label` varchar(120) DEFAULT NULL,
  `is_lead` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_ops_board_assignments` (`item_id`,`user_id`,`role_label`),
  KEY `idx_ops_board_assignments_item` (`item_id`,`is_lead`),
  CONSTRAINT `fk_ops_board_assignments_item` FOREIGN KEY (`item_id`) REFERENCES `ops_board_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ops_board_assignments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ops_board_assets` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `item_id` int unsigned NOT NULL,
  `label` varchar(180) NOT NULL,
  `type` varchar(80) NOT NULL,
  `reference` varchar(512) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ops_board_assets_item` (`item_id`),
  CONSTRAINT `fk_ops_board_assets_item` FOREIGN KEY (`item_id`) REFERENCES `ops_board_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ops_board_audience` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `item_id` int unsigned NOT NULL,
  `audience_type` enum('tenant','unit','role','global') NOT NULL,
  `audience_value` varchar(191) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ops_board_audience_item` (`item_id`,`audience_type`),
  KEY `idx_ops_board_audience_lookup` (`audience_type`,`audience_value`),
  CONSTRAINT `fk_ops_board_audience_item` FOREIGN KEY (`item_id`) REFERENCES `ops_board_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ops_board_history` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `item_id` int unsigned NOT NULL,
  `actor_user_id` int unsigned DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `before_json` json DEFAULT NULL,
  `after_json` json DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ops_board_history_item` (`item_id`,`created_at`),
  CONSTRAINT `fk_ops_board_history_item` FOREIGN KEY (`item_id`) REFERENCES `ops_board_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ops_board_history_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
