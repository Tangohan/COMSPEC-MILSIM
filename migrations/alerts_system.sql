-- Alertes plateforme + communauté + dismissals utilisateur
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `platform_alerts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `kind` enum('discount','novelty','info','urgent') NOT NULL DEFAULT 'info',
  `title` varchar(255) NOT NULL,
  `body` text,
  `cta_label` varchar(120) DEFAULT NULL,
  `cta_url` varchar(512) DEFAULT NULL,
  `coupon_code` varchar(64) DEFAULT NULL,
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `dismissible` tinyint(1) NOT NULL DEFAULT 1,
  `email_last_sent_at` datetime DEFAULT NULL,
  `email_last_sent_count` int unsigned NOT NULL DEFAULT 0,
  `audience_json` json DEFAULT NULL COMMENT '{"guest":bool,"authenticated":bool,"free":bool,"paid":bool}',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_platform_alerts_active` (`is_active`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tenant_alerts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `kind` varchar(32) NOT NULL DEFAULT 'info',
  `title` varchar(255) NOT NULL,
  `body` text,
  `cta_label` varchar(120) DEFAULT NULL,
  `cta_url` varchar(512) DEFAULT NULL,
  `coupon_code` varchar(64) DEFAULT NULL,
  `accent_color` varchar(7) DEFAULT NULL,
  `icon_key` varchar(32) DEFAULT NULL,
  `image_path` varchar(512) DEFAULT NULL,
  `banner_path` varchar(512) DEFAULT NULL,
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_alerts_tenant` (`tenant_id`,`is_active`,`sort_order`),
  CONSTRAINT `tenant_alerts_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_alert_dismissals` (
  `user_id` int unsigned NOT NULL,
  `scope` enum('platform','tenant') NOT NULL,
  `alert_id` int unsigned NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`,`scope`,`alert_id`),
  CONSTRAINT `user_alert_dismissals_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
