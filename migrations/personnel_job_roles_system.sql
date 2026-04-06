-- Rôles métier dossier personnel : catégories / sous-catégories, rôles nommés, droits (permissions tenant) liés.
-- Distinct des rôles communauté (`roles` / users.role_id).

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `personnel_job_role_categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `parent_id` int unsigned DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `sort_order` int NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pjrc_tenant_slug` (`tenant_id`,`slug`),
  KEY `pjrc_tenant_parent` (`tenant_id`,`parent_id`),
  CONSTRAINT `pjrc_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `pjrc_parent_fk` FOREIGN KEY (`parent_id`) REFERENCES `personnel_job_role_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `personnel_job_roles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `category_id` int unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT 0,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pjr_tenant_slug` (`tenant_id`,`slug`),
  KEY `pjr_tenant_cat` (`tenant_id`,`category_id`),
  CONSTRAINT `pjr_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `pjr_category_fk` FOREIGN KEY (`category_id`) REFERENCES `personnel_job_role_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `personnel_job_role_permissions` (
  `personnel_job_role_id` int unsigned NOT NULL,
  `permission_id` int unsigned NOT NULL,
  PRIMARY KEY (`personnel_job_role_id`,`permission_id`),
  KEY `pjrp_perm` (`permission_id`),
  CONSTRAINT `pjrp_role_fk` FOREIGN KEY (`personnel_job_role_id`) REFERENCES `personnel_job_roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `pjrp_perm_fk` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
