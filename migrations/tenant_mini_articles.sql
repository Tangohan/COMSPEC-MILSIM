-- Mini-articles communauté (contenu permanent : titre, tags, description, HTML, images)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `tenant_mini_articles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `author_user_id` INT UNSIGNED NULL DEFAULT NULL,
  `title` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(280) NOT NULL,
  `excerpt` TEXT NULL,
  `body_html` MEDIUMTEXT NOT NULL,
  `tags_json` JSON NULL,
  `cover_path` VARCHAR(512) NULL DEFAULT NULL,
  `gallery_json` JSON NULL,
  `status` ENUM('draft','published') NOT NULL DEFAULT 'draft',
  `published_at` DATETIME NULL DEFAULT NULL,
  `pinned` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tma_tenant_slug` (`tenant_id`,`slug`),
  KEY `idx_tma_tenant_status_pub` (`tenant_id`,`status`,`published_at`),
  KEY `idx_tma_tenant_pinned` (`tenant_id`,`pinned`,`published_at`),
  CONSTRAINT `tma_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
