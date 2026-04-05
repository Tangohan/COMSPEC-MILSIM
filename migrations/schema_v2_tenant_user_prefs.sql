-- V2 : préférences UI, notifications, configuration tenant typée (industrialisation schéma)
-- Exécution : run-migrations.php (après schéma de base)
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Préférences UI utilisateur (1:1 user dans un tenant)
CREATE TABLE IF NOT EXISTS `user_ui_preferences` (
  `user_id` int unsigned NOT NULL,
  `tenant_id` int unsigned NOT NULL,
  `theme` varchar(32) NOT NULL DEFAULT 'system' COMMENT 'system|light|dark|tenant',
  `density` varchar(16) NOT NULL DEFAULT 'comfortable' COMMENT 'compact|comfortable',
  `sidebar_collapsed` tinyint(1) NOT NULL DEFAULT 0,
  `dashboard_layout_json` json DEFAULT NULL COMMENT '{schema_version:int, widgets:[...]}',
  `favorite_modules_json` json DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  KEY `idx_uui_tenant` (`tenant_id`),
  CONSTRAINT `user_ui_preferences_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `user_ui_preferences_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Préférences notifications (granularité canal × événement)
CREATE TABLE IF NOT EXISTS `user_notification_preferences` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `tenant_id` int unsigned NOT NULL,
  `channel` enum('email','in_app','push') NOT NULL DEFAULT 'in_app',
  `event_key` varchar(80) NOT NULL COMMENT 'forum.reply|courrier.sent|...',
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_unp_user_channel_event` (`user_id`,`channel`,`event_key`),
  KEY `idx_unp_tenant` (`tenant_id`),
  CONSTRAINT `unp_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `unp_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Branding : logo_url peut refléter tenants.logo_url (première synchro en migration PHP) ; champs étendus ici
CREATE TABLE IF NOT EXISTS `tenant_branding` (
  `tenant_id` int unsigned NOT NULL,
  `logo_url` varchar(500) DEFAULT NULL,
  `banner_url` varchar(500) DEFAULT NULL,
  `primary_color` char(7) DEFAULT NULL COMMENT '#RRGGBB',
  `accent_color` char(7) DEFAULT NULL,
  `favicon_url` varchar(500) DEFAULT NULL,
  `public_home_hero_json` json DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`tenant_id`),
  CONSTRAINT `tenant_branding_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tenant_security_policy` (
  `tenant_id` int unsigned NOT NULL,
  `password_min_length` tinyint unsigned NOT NULL DEFAULT 12,
  `session_idle_timeout_minutes` int unsigned NOT NULL DEFAULT 480,
  `lockout_max_attempts` tinyint unsigned NOT NULL DEFAULT 8,
  `require_email_verified_for_enlistment` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`tenant_id`),
  CONSTRAINT `tenant_security_policy_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tenant_module_entitlements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `module_key` varchar(64) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `config_schema_version` smallint unsigned NOT NULL DEFAULT 1,
  `config_json` json DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tme_tenant_module` (`tenant_id`,`module_key`),
  KEY `idx_tme_enabled` (`tenant_id`,`enabled`),
  CONSTRAINT `tme_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tenant_quotas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `metric_key` varchar(64) NOT NULL,
  `limit_value` bigint NOT NULL DEFAULT 0,
  `period` enum('none','day','month') NOT NULL DEFAULT 'none',
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tq_tenant_metric_period` (`tenant_id`,`metric_key`,`period`),
  CONSTRAINT `tq_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
