-- Athena : schéma complet (sans Phinx)
-- Exécution : run-migrations.php ou import manuel

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `tenants` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `logo_url` varchar(500) DEFAULT NULL,
  `settings` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `roles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `is_system` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_id_slug` (`tenant_id`,`slug`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `roles_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `permissions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `module` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_id_slug` (`tenant_id`,`slug`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `permissions_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role_id` int unsigned NOT NULL,
  `permission_id` int unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `permission_id` (`permission_id`),
  CONSTRAINT `role_permissions_role_id_fk` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_permissions_permission_id_fk` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `grades` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `short_name` varchar(20) NOT NULL,
  `rank_order` int DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `grades_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `display_name` varchar(100) DEFAULT NULL,
  `callsign` varchar(50) DEFAULT NULL,
  `avatar_url` varchar(500) DEFAULT NULL,
  `role_id` int unsigned DEFAULT NULL,
  `grade_id` int unsigned DEFAULT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_id_email` (`tenant_id`,`email`),
  KEY `tenant_id` (`tenant_id`),
  KEY `role_id` (`role_id`),
  KEY `grade_id` (`grade_id`),
  CONSTRAINT `users_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `users_role_id_fk` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `users_grade_id_fk` FOREIGN KEY (`grade_id`) REFERENCES `grades` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `sessions` (
  `id` varchar(128) NOT NULL,
  `user_id` int unsigned NOT NULL,
  `tenant_id` int unsigned NOT NULL,
  `payload` text NOT NULL,
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id_tenant_id` (`user_id`,`tenant_id`),
  CONSTRAINT `sessions_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sessions_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `token_hash` (`token_hash`),
  CONSTRAINT `password_resets_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `success` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `email_created_at` (`email`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned DEFAULT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int unsigned DEFAULT NULL,
  `old_value` text,
  `new_value` text,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tenant_id_created_at` (`tenant_id`,`created_at`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Units & profiles
CREATE TABLE IF NOT EXISTS `units` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `parent_id` int unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `type` varchar(50) DEFAULT NULL,
  `code` varchar(20) DEFAULT NULL,
  `commander_user_id` int unsigned DEFAULT NULL,
  `display_order` int DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_id_slug` (`tenant_id`,`slug`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `units_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `user_units` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `unit_id` int unsigned NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `assigned_by` int unsigned DEFAULT NULL,
  `assigned_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `ended_at` datetime DEFAULT NULL,
  `assignment_type` varchar(50) DEFAULT NULL,
  `notes` text,
  PRIMARY KEY (`id`),
  KEY `user_id_unit_id` (`user_id`,`unit_id`),
  CONSTRAINT `user_units_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_units_unit_id_fk` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `user_profiles` (
  `user_id` int unsigned NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `nationality` varchar(100) DEFAULT NULL,
  `timezone` varchar(50) DEFAULT NULL,
  `language` varchar(10) DEFAULT NULL,
  `bio` text,
  `phone` varchar(50) DEFAULT NULL,
  `emergency_contact` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `user_profiles_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `personnel_extras` (
  `user_id` int unsigned NOT NULL,
  `service_number` varchar(50) DEFAULT NULL,
  `squadron` varchar(100) DEFAULT NULL,
  `date_of_enlistment` date DEFAULT NULL,
  `clearance_level` varchar(100) DEFAULT NULL,
  `flight_hours` decimal(10,1) DEFAULT NULL,
  `specializations` text,
  `readiness_percent` int DEFAULT NULL,
  `admin_notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `personnel_extras_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Enlistments
CREATE TABLE IF NOT EXISTS `enlistments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `callsign` varchar(50) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `experience` varchar(255) DEFAULT NULL,
  `specialty` varchar(255) DEFAULT NULL,
  `platform` varchar(255) DEFAULT NULL,
  `availability` varchar(255) DEFAULT NULL,
  `notes` text,
  `status` varchar(50) DEFAULT 'submitted',
  `reviewed_by` int unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `reviewer_comment` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tenant_id_status` (`tenant_id`,`status`),
  CONSTRAINT `enlistments_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Documents
CREATE TABLE IF NOT EXISTS `document_categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_id_slug` (`tenant_id`,`slug`),
  CONSTRAINT `document_categories_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `documents` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `document_category_id` int unsigned DEFAULT NULL,
  `status` varchar(50) DEFAULT 'draft',
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_id_slug` (`tenant_id`,`slug`),
  CONSTRAINT `documents_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `documents_category_fk` FOREIGN KEY (`document_category_id`) REFERENCES `document_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `document_versions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `document_id` int unsigned NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `checksum` varchar(64) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `size` int unsigned DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `change_notes` text,
  `published_at` datetime DEFAULT NULL,
  `is_current` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `document_id_is_current` (`document_id`,`is_current`),
  CONSTRAINT `document_versions_document_id_fk` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Training
CREATE TABLE IF NOT EXISTS `training_modules` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `description` text,
  `type` varchar(50) DEFAULT 'html',
  `status` varchar(50) DEFAULT 'published',
  `estimated_duration_min` int DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_id_slug` (`tenant_id`,`slug`),
  CONSTRAINT `training_modules_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `training_progress` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `training_module_id` int unsigned NOT NULL,
  `progress_percent` int DEFAULT 0,
  `status` varchar(50) DEFAULT 'in_progress',
  `started_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `last_activity_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id_training_module_id` (`user_id`,`training_module_id`),
  CONSTRAINT `training_progress_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `training_progress_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `training_progress_module_fk` FOREIGN KEY (`training_module_id`) REFERENCES `training_modules` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `training_certificates` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `training_module_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `certificate_code` varchar(50) NOT NULL,
  `issued_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime DEFAULT NULL,
  `issued_by` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id_training_module_id` (`user_id`,`training_module_id`),
  CONSTRAINT `training_certificates_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `training_certificates_module_fk` FOREIGN KEY (`training_module_id`) REFERENCES `training_modules` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `training_certificates_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
