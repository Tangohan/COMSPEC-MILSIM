-- Dossier personnel opérationnel : personnel_profiles, qualifications, affectations, historique
-- Exécution : run-migrations.php (après schema.sql)
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Profil opérationnel / RP (1:1 avec users)
CREATE TABLE IF NOT EXISTS `personnel_profiles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `character_name` varchar(150) DEFAULT NULL,
  `callsign` varchar(100) DEFAULT NULL,
  `rank_display` varchar(100) DEFAULT NULL,
  `primary_role` varchar(100) DEFAULT NULL,
  `secondary_role` varchar(100) DEFAULT NULL,
  `primary_unit_id` int unsigned DEFAULT NULL,
  `clearance_level` varchar(50) DEFAULT NULL,
  `character_portrait_path` varchar(255) DEFAULT NULL,
  `character_banner_path` varchar(255) DEFAULT NULL,
  `blood_type` varchar(10) DEFAULT NULL,
  `nationality` varchar(100) DEFAULT NULL,
  `languages` varchar(255) DEFAULT NULL,
  `enlistment_date` date DEFAULT NULL,
  `motto` varchar(255) DEFAULT NULL,
  `readiness_score` tinyint unsigned DEFAULT 0,
  `command_notes` longtext DEFAULT NULL,
  `matricule_internal` varchar(100) DEFAULT NULL,
  `clearance_reviewed_at` datetime DEFAULT NULL,
  `equipment_class` varchar(100) DEFAULT NULL,
  `kit_assigned` varchar(255) DEFAULT NULL,
  `radio_assigned` varchar(100) DEFAULT NULL,
  `vehicle_authorized` varchar(255) DEFAULT NULL,
  `weapon_specialty` varchar(100) DEFAULT NULL,
  `deployable` tinyint(1) DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personnel_profiles_user_id` (`user_id`),
  KEY `personnel_profiles_primary_unit` (`primary_unit_id`),
  CONSTRAINT `personnel_profiles_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `personnel_profiles_primary_unit_fk` FOREIGN KEY (`primary_unit_id`) REFERENCES `units` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Qualifications / certifications métier (TCCC, JTAC, Radio, etc.)
CREATE TABLE IF NOT EXISTS `personnel_qualifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `qualification_name` varchar(150) NOT NULL,
  `level` varchar(50) DEFAULT NULL,
  `status` enum('valid','expiring','expired','in_progress') DEFAULT 'valid',
  `obtained_at` date DEFAULT NULL,
  `expires_at` date DEFAULT NULL,
  `issued_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `personnel_qualifications_user` (`user_id`),
  KEY `personnel_qualifications_status` (`status`),
  CONSTRAINT `personnel_qualifications_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Affectations détaillées (rôle par unité ; complète user_units)
CREATE TABLE IF NOT EXISTS `personnel_assignments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `unit_id` int unsigned NOT NULL,
  `role_name` varchar(100) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 1,
  `started_at` date DEFAULT NULL,
  `ended_at` date DEFAULT NULL,
  `status` enum('active','inactive','pending') DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `personnel_assignments_user` (`user_id`),
  KEY `personnel_assignments_unit` (`unit_id`),
  CONSTRAINT `personnel_assignments_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `personnel_assignments_unit_fk` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Historique de service (timeline)
CREATE TABLE IF NOT EXISTS `personnel_service_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `event_type` enum('assignment','promotion','qualification','deployment','award','discipline','note') NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `event_date` date NOT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `personnel_service_history_user` (`user_id`),
  KEY `personnel_service_history_date` (`event_date`),
  CONSTRAINT `personnel_service_history_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Médias profil (portrait, bannière, patch, signature, fullbody) — extension
CREATE TABLE IF NOT EXISTS `personnel_media` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `media_type` enum('avatar','portrait','banner','patch','signature','fullbody') NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `personnel_media_user_type` (`user_id`,`media_type`),
  CONSTRAINT `personnel_media_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
