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
  `is_locked` tinyint(1) DEFAULT 0,
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
  `steam_id` varchar(20) DEFAULT NULL,
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
  `arma_callsign` varchar(100) DEFAULT NULL,
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

-- Enlistments (colonnes de base ; colonnes Olympus ajoutées par ALTER en fin de fichier)
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
  `color` varchar(50) DEFAULT NULL,
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
  `description` text,
  `document_category_id` int unsigned DEFAULT NULL,
  `status` varchar(50) DEFAULT 'draft',
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_id_slug` (`tenant_id`,`slug`),
  KEY `tenant_id` (`tenant_id`),
  KEY `slug` (`slug`),
  CONSTRAINT `documents_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `documents_category_fk` FOREIGN KEY (`document_category_id`) REFERENCES `document_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `document_versions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `document_id` int unsigned NOT NULL,
  `version_number` int unsigned NOT NULL DEFAULT 1,
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
  KEY `document_id` (`document_id`),
  KEY `document_id_is_current` (`document_id`,`is_current`),
  CONSTRAINT `document_versions_document_id_fk` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `equipment_classes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `description` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_id_slug` (`tenant_id`,`slug`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `equipment_classes_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `document_links` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `document_id` int unsigned NOT NULL,
  `entity_type` enum('training','equipment_class','unit','user') NOT NULL,
  `entity_id` int unsigned NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `document_id` (`document_id`),
  KEY `entity_type` (`entity_type`),
  KEY `entity_id` (`entity_id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `document_links_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `document_links_document_id_fk` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
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

-- ============================================================
-- Colonnes formulaire Olympus (age, timezone, etc.) : ajout conditionnel dans run-migrations.php si absentes.

-- ============================================================
-- Matricules personnalisables (par tenant)
-- format_pattern : ex. "{prefix}-{seq}" ou "FOG-{seq:5}" (5 chiffres)
-- ============================================================
CREATE TABLE IF NOT EXISTS `tenant_matricule_config` (
  `tenant_id` int unsigned NOT NULL,
  `prefix` varchar(20) DEFAULT '',
  `format_pattern` varchar(80) NOT NULL DEFAULT '{prefix}-{seq}',
  `next_number` int unsigned NOT NULL DEFAULT 1,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`tenant_id`),
  CONSTRAINT `tenant_matricule_config_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Configuration ATAK / Arma par équipe (tenant)
CREATE TABLE IF NOT EXISTS `tenant_atak_config` (
  `tenant_id` int unsigned NOT NULL,
  `node_url` varchar(500) DEFAULT NULL,
  `jwt_secret` varchar(255) DEFAULT NULL,
  `arma_server_host` varchar(255) DEFAULT NULL,
  `arma_server_port` smallint unsigned DEFAULT NULL,
  `arma_mod_credentials` text DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`tenant_id`),
  CONSTRAINT `tenant_atak_config_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cartes ATAK (globales, liste des cartes disponibles pour l'overlay)
CREATE TABLE IF NOT EXISTS `atak_maps` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(50) NOT NULL,
  `label` varchar(100) NOT NULL,
  `world_name` varchar(50) NOT NULL DEFAULT 'altis',
  `tile_pattern` varchar(500) NOT NULL,
  `config` json DEFAULT NULL,
  `display_order` int NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Données ATAK live (parité Node, multi-tenant)
CREATE TABLE IF NOT EXISTS `atak_layers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `map_id` int unsigned NOT NULL DEFAULT 1,
  `label` varchar(255) NOT NULL,
  `phase` int DEFAULT NULL,
  `order` int NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tenant_map` (`tenant_id`,`map_id`),
  CONSTRAINT `atak_layers_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `atak_markers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `map_id` int unsigned NOT NULL DEFAULT 1,
  `layer_id` int unsigned NOT NULL DEFAULT 1,
  `marker_data` text NOT NULL,
  `arma_name` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tenant_map` (`tenant_id`,`map_id`),
  CONSTRAINT `atak_markers_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `atak_units` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `map_id` int unsigned NOT NULL DEFAULT 1,
  `call_sign` varchar(255) NOT NULL,
  `role` varchar(255) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'linked',
  `grid_ref` varchar(100) DEFAULT NULL,
  `heading` decimal(10,4) DEFAULT NULL,
  `extra` json DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tenant_map` (`tenant_id`,`map_id`),
  KEY `map_callsign` (`map_id`,`call_sign`),
  CONSTRAINT `atak_units_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `atak_chat_messages` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `map_id` int unsigned NOT NULL DEFAULT 1,
  `author` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tenant_map` (`tenant_id`,`map_id`),
  CONSTRAINT `atak_chat_messages_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `atak_pings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `map_id` int unsigned NOT NULL DEFAULT 1,
  `author` varchar(255) NOT NULL,
  `pos_x` decimal(15,4) NOT NULL,
  `pos_y` decimal(15,4) NOT NULL,
  `message` text DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tenant_map` (`tenant_id`,`map_id`),
  CONSTRAINT `atak_pings_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `atak_nine_line` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `map_id` int unsigned NOT NULL DEFAULT 1,
  `mission_id` varchar(128) DEFAULT NULL,
  `author` varchar(255) NOT NULL,
  `assigned_aircraft` varchar(128) DEFAULT NULL,
  `line1` varchar(255) DEFAULT NULL,
  `line2` varchar(255) DEFAULT NULL,
  `line3` varchar(255) DEFAULT NULL,
  `line4` varchar(255) DEFAULT NULL,
  `line5` varchar(255) DEFAULT NULL,
  `line6` varchar(255) DEFAULT NULL,
  `line7` varchar(255) DEFAULT NULL,
  `line8` varchar(255) DEFAULT NULL,
  `line9` text DEFAULT NULL,
  `lines_checked` json DEFAULT NULL,
  `status` varchar(50) DEFAULT 'DRAFT',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tenant_map` (`tenant_id`,`map_id`),
  KEY `assigned_aircraft` (`assigned_aircraft`),
  CONSTRAINT `atak_nine_line_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `atak_intel_photos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `map_id` int unsigned NOT NULL DEFAULT 1,
  `filename` varchar(255) NOT NULL,
  `path` varchar(500) NOT NULL,
  `author` varchar(255) DEFAULT NULL,
  `pos_x` decimal(15,4) DEFAULT NULL,
  `pos_y` decimal(15,4) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tenant_map` (`tenant_id`,`map_id`),
  CONSTRAINT `atak_intel_photos_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `atak_designator_targets` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `map_id` int unsigned NOT NULL DEFAULT 1,
  `call_sign` varchar(255) NOT NULL,
  `pos_x` decimal(15,4) NOT NULL,
  `pos_y` decimal(15,4) NOT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_map_callsign` (`tenant_id`,`map_id`,`call_sign`),
  CONSTRAINT `atak_designator_targets_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `atak_sigint_reports` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `map_id` int unsigned NOT NULL DEFAULT 1,
  `call_sign` varchar(255) NOT NULL,
  `pos_x` decimal(15,4) NOT NULL,
  `pos_y` decimal(15,4) NOT NULL,
  `bearing` decimal(10,4) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tenant_map` (`tenant_id`,`map_id`),
  CONSTRAINT `atak_sigint_reports_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `atak_last_activity` (
  `tenant_id` int unsigned NOT NULL,
  `map_id` int unsigned NOT NULL DEFAULT 1,
  `last_activity_at` datetime NOT NULL,
  PRIMARY KEY (`tenant_id`,`map_id`),
  CONSTRAINT `atak_last_activity_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `atak_air_assets` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `map_id` int unsigned NOT NULL DEFAULT 1,
  `mission_id` varchar(128) DEFAULT NULL,
  `callsign` varchar(128) NOT NULL,
  `model` varchar(255) DEFAULT NULL,
  `aircraft_type` varchar(32) DEFAULT NULL,
  `freq` varchar(64) DEFAULT NULL,
  `radio_main` varchar(64) DEFAULT NULL,
  `radio_aux` varchar(64) DEFAULT NULL,
  `laser` varchar(32) DEFAULT '1688',
  `auth` varchar(128) DEFAULT NULL,
  `auth_code` varchar(128) DEFAULT NULL,
  `pilot` varchar(255) DEFAULT NULL,
  `crew` json DEFAULT NULL,
  `fuel_pct` int unsigned DEFAULT NULL,
  `ordnance` json DEFAULT NULL,
  `station` varchar(128) DEFAULT NULL,
  `eta_minutes` int unsigned DEFAULT NULL,
  `bingo_fuel` varchar(32) DEFAULT NULL,
  `checklist` json DEFAULT NULL,
  `pos_x` decimal(15,4) DEFAULT NULL,
  `pos_y` decimal(15,4) DEFAULT NULL,
  `pos_z` decimal(15,4) DEFAULT NULL,
  `alt` decimal(10,2) DEFAULT NULL,
  `heading` decimal(8,2) DEFAULT NULL,
  `side` varchar(16) DEFAULT 'WEST',
  `status` varchar(32) DEFAULT 'AVAILABLE',
  `pilot_status` varchar(32) DEFAULT NULL,
  `aircraft_count` int unsigned DEFAULT 1,
  `last_update` bigint DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_map_callsign` (`tenant_id`,`map_id`,`callsign`),
  KEY `tenant_map` (`tenant_id`,`map_id`),
  CONSTRAINT `atak_air_assets_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `recon_images` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `mission_id` varchar(128) DEFAULT NULL,
  `author_callsign` varchar(128) NOT NULL,
  `unit_name` varchar(255) DEFAULT NULL,
  `side` varchar(16) DEFAULT 'WEST',
  `image_path` varchar(500) NOT NULL,
  `thumb_path` varchar(500) DEFAULT NULL,
  `caption` text DEFAULT NULL,
  `pos_x` decimal(15,4) DEFAULT NULL,
  `pos_y` decimal(15,4) DEFAULT NULL,
  `pos_z` decimal(15,4) DEFAULT NULL,
  `grid_ref` varchar(32) DEFAULT NULL,
  `heading` decimal(8,2) DEFAULT NULL,
  `altitude` decimal(10,2) DEFAULT NULL,
  `device_type` varchar(64) DEFAULT 'CTAB',
  `captured_at` datetime DEFAULT NULL,
  `atak_cas_id` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tenant_mission` (`tenant_id`,`mission_id`),
  KEY `author_callsign` (`author_callsign`),
  KEY `captured_at` (`captured_at`),
  CONSTRAINT `recon_images_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `atak_map_shapes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `map_id` int unsigned NOT NULL DEFAULT 1,
  `mission_id` varchar(128) DEFAULT NULL,
  `shape_uid` varchar(64) NOT NULL,
  `type` varchar(32) NOT NULL,
  `label` varchar(255) DEFAULT NULL,
  `color` varchar(32) DEFAULT '#3388ff',
  `stroke` int unsigned DEFAULT 2,
  `fill_opacity` decimal(3,2) DEFAULT 0.15,
  `created_by` varchar(128) DEFAULT NULL,
  `visible_to` json DEFAULT NULL,
  `geometry` json NOT NULL,
  `meta` json DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_map_uid` (`tenant_id`,`map_id`,`shape_uid`),
  KEY `tenant_map` (`tenant_id`,`map_id`),
  CONSTRAINT `atak_map_shapes_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `atak_laser_codes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `map_id` int unsigned NOT NULL DEFAULT 1,
  `call_sign` varchar(128) NOT NULL,
  `laser_code` varchar(32) NOT NULL,
  `pos_x` decimal(15,4) DEFAULT NULL,
  `pos_y` decimal(15,4) DEFAULT NULL,
  `status` varchar(32) DEFAULT 'ACTIVE',
  `last_update` bigint DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_map_callsign` (`tenant_id`,`map_id`,`call_sign`),
  KEY `tenant_map` (`tenant_id`,`map_id`),
  CONSTRAINT `atak_laser_codes_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Colonne nato_code sur grades : ajout conditionnel dans run-migrations.php (compatible ancienne table ou grades_referentiel renommée).

-- Panneaux de données administratives (définis par tenant)
CREATE TABLE IF NOT EXISTS `personnel_admin_panels` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `display_order` int DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_id_slug` (`tenant_id`,`slug`),
  CONSTRAINT `personnel_admin_panels_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Données par panel et par utilisateur (JSON flexible)
CREATE TABLE IF NOT EXISTS `personnel_admin_data` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `panel_id` int unsigned NOT NULL,
  `data` json DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id_panel_id` (`user_id`,`panel_id`),
  CONSTRAINT `personnel_admin_data_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `personnel_admin_data_panel_id_fk` FOREIGN KEY (`panel_id`) REFERENCES `personnel_admin_panels` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Forum
CREATE TABLE IF NOT EXISTS `forum_categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `parent_id` int unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text,
  `icon` varchar(50) DEFAULT NULL,
  `color_theme` varchar(50) DEFAULT 'slate',
  `display_order` int DEFAULT 0,
  `is_locked` tinyint(1) DEFAULT 0,
  `min_role_id` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  KEY `parent_id` (`parent_id`),
  UNIQUE KEY `tenant_id_slug` (`tenant_id`,`slug`),
  CONSTRAINT `forum_categories_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `forum_categories_parent_id_fk` FOREIGN KEY (`parent_id`) REFERENCES `forum_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `forum_categories_min_role_id_fk` FOREIGN KEY (`min_role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `forum_topics` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `category_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `title` varchar(500) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `is_pinned` tinyint(1) DEFAULT 0,
  `is_locked` tinyint(1) DEFAULT 0,
  `is_archived` tinyint(1) DEFAULT 0,
  `is_hidden` tinyint(1) DEFAULT 0,
  `view_count` int unsigned DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  KEY `category_id` (`category_id`),
  KEY `category_updated` (`category_id`,`updated_at`),
  CONSTRAINT `forum_topics_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `forum_topics_category_id_fk` FOREIGN KEY (`category_id`) REFERENCES `forum_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `forum_topics_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `forum_posts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `topic_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `body` text NOT NULL,
  `is_hidden` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  KEY `topic_id` (`topic_id`),
  KEY `topic_created` (`topic_id`,`created_at`),
  CONSTRAINT `forum_posts_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `forum_posts_topic_id_fk` FOREIGN KEY (`topic_id`) REFERENCES `forum_topics` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `forum_posts_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `forum_topic_subscriptions` (
  `user_id` int unsigned NOT NULL,
  `topic_id` int unsigned NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`,`topic_id`),
  CONSTRAINT `forum_topic_subscriptions_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `forum_topic_subscriptions_topic_id_fk` FOREIGN KEY (`topic_id`) REFERENCES `forum_topics` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `forum_category_subscriptions` (
  `user_id` int unsigned NOT NULL,
  `category_id` int unsigned NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`,`category_id`),
  CONSTRAINT `forum_category_subscriptions_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `forum_category_subscriptions_category_id_fk` FOREIGN KEY (`category_id`) REFERENCES `forum_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `forum_reports` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `reporter_id` int unsigned NOT NULL,
  `post_id` int unsigned DEFAULT NULL,
  `topic_id` int unsigned DEFAULT NULL,
  `reason` text,
  `status` varchar(20) DEFAULT 'pending',
  `handled_by` int unsigned DEFAULT NULL,
  `handled_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  KEY `status` (`status`),
  CONSTRAINT `forum_reports_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `forum_reports_reporter_id_fk` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `forum_reports_post_id_fk` FOREIGN KEY (`post_id`) REFERENCES `forum_posts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `forum_reports_topic_id_fk` FOREIGN KEY (`topic_id`) REFERENCES `forum_topics` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `forum_reports_handled_by_fk` FOREIGN KEY (`handled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `forum_read` (
  `user_id` int unsigned NOT NULL,
  `topic_id` int unsigned NOT NULL,
  `read_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`,`topic_id`),
  CONSTRAINT `forum_read_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `forum_read_topic_id_fk` FOREIGN KEY (`topic_id`) REFERENCES `forum_topics` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Modpacks (par tenant)
CREATE TABLE IF NOT EXISTS `modpacks` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `url` varchar(500) DEFAULT NULL,
  `version` varchar(50) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `size` int unsigned DEFAULT NULL,
  `released_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `description` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_by` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_id_slug` (`tenant_id`,`slug`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `modpacks_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `modpacks_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `modpack_images` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `modpack_id` int unsigned NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `display_order` int DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `modpack_id` (`modpack_id`),
  CONSTRAINT `modpack_images_modpack_id_fk` FOREIGN KEY (`modpack_id`) REFERENCES `modpacks` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Paramètres par tenant (clé/valeur, ex. forum_*)
CREATE TABLE IF NOT EXISTS `site_settings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `key` varchar(100) NOT NULL,
  `value` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_key` (`tenant_id`,`key`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `site_settings_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Mots bannis (modération forum)
CREATE TABLE IF NOT EXISTS `forum_banned_words` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `word` varchar(255) NOT NULL,
  `severity` varchar(20) DEFAULT 'block',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `forum_banned_words_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Domaines blacklistés (liens dans les messages)
CREATE TABLE IF NOT EXISTS `forum_blacklisted_domains` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `domain` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `forum_blacklisted_domains_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
