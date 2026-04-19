-- Centre de commandement admin : journal d'actions sensibles, annulation et compensation.
-- Idempotent ; n’écrase pas les tables existantes.

CREATE TABLE IF NOT EXISTS `admin_actions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED DEFAULT NULL,
  `actor_user_id` INT UNSIGNED NOT NULL,
  `action_type` VARCHAR(120) NOT NULL,
  `target_type` VARCHAR(80) NOT NULL,
  `target_id` VARCHAR(80) DEFAULT NULL,
  `scope` VARCHAR(32) NOT NULL DEFAULT 'platform',
  `status` VARCHAR(24) NOT NULL DEFAULT 'applied',
  `reason` TEXT NULL,
  `before_json` JSON NULL,
  `after_json` JSON NULL,
  `is_undoable` TINYINT(1) NOT NULL DEFAULT 0,
  `is_compensable` TINYINT(1) NOT NULL DEFAULT 0,
  `non_reversible_reason` VARCHAR(255) NULL,
  `undo_strategy` VARCHAR(64) NULL,
  `ip_address` VARCHAR(45) NULL,
  `session_id` VARCHAR(128) NULL,
  `executed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_admin_actions_actor` (`actor_user_id`,`created_at`),
  KEY `idx_admin_actions_type` (`action_type`,`created_at`),
  KEY `idx_admin_actions_target` (`target_type`,`target_id`),
  KEY `idx_admin_actions_undo` (`is_undoable`,`status`,`created_at`),
  KEY `idx_admin_actions_tenant` (`tenant_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admin_action_undo` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_action_id` BIGINT UNSIGNED NOT NULL,
  `requested_by_user_id` INT UNSIGNED NOT NULL,
  `reason` VARCHAR(255) NOT NULL,
  `status` VARCHAR(24) NOT NULL DEFAULT 'pending',
  `executed_at` DATETIME NULL,
  `result_message` VARCHAR(500) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_admin_action_undo_lookup` (`admin_action_id`,`status`),
  CONSTRAINT `fk_admin_action_undo_action` FOREIGN KEY (`admin_action_id`) REFERENCES `admin_actions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admin_action_compensations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_action_id` BIGINT UNSIGNED NOT NULL,
  `created_by_user_id` INT UNSIGNED NOT NULL,
  `compensation_type` VARCHAR(64) NOT NULL,
  `details_json` JSON NULL,
  `status` VARCHAR(24) NOT NULL DEFAULT 'applied',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_admin_compensation_lookup` (`admin_action_id`,`status`,`created_at`),
  CONSTRAINT `fk_admin_action_compensation_action` FOREIGN KEY (`admin_action_id`) REFERENCES `admin_actions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `page_registry` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(190) NOT NULL,
  `route_path` VARCHAR(255) NOT NULL,
  `display_name` VARCHAR(190) NOT NULL,
  `owner_team` VARCHAR(120) NULL,
  `state` VARCHAR(32) NOT NULL DEFAULT 'active',
  `visibility` VARCHAR(32) NOT NULL DEFAULT 'internal',
  `access_level` VARCHAR(64) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `is_maintenance` TINYINT(1) NOT NULL DEFAULT 0,
  `version` VARCHAR(40) NULL,
  `last_modified_by` INT UNSIGNED NULL,
  `last_modified_at` DATETIME NULL,
  `meta_json` JSON NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_page_registry_slug` (`slug`),
  KEY `idx_page_registry_state` (`state`,`visibility`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `page_state_history` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `page_id` BIGINT UNSIGNED NOT NULL,
  `changed_by_user_id` INT UNSIGNED NOT NULL,
  `old_state` VARCHAR(32) NULL,
  `new_state` VARCHAR(32) NOT NULL,
  `reason` VARCHAR(255) NULL,
  `meta_json` JSON NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_page_state_history_page` (`page_id`,`created_at`),
  CONSTRAINT `fk_page_state_history_page` FOREIGN KEY (`page_id`) REFERENCES `page_registry` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
