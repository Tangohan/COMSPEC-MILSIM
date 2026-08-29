-- Dossier RH individuel (documents, mobilité, vivier, offboarding v2).
-- Idempotent via bootstrap/rh_dossier_individuel_migration.php.

CREATE TABLE IF NOT EXISTS `personnel_hr_documents` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `doc_type` enum('candidature','charte','reglement','certificat','qualification','affectation','evaluation','autre') NOT NULL DEFAULT 'autre',
  `title` varchar(200) NOT NULL,
  `description` text,
  `file_path` varchar(500) DEFAULT NULL,
  `original_name` varchar(255) DEFAULT NULL,
  `visibility` enum('MEMBER','STAFF') NOT NULL DEFAULT 'STAFF',
  `uploaded_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `archived_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_hr_docs_user` (`tenant_id`,`user_id`,`doc_type`),
  KEY `idx_hr_docs_type` (`tenant_id`,`doc_type`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `personnel_mobility_requests` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `request_type` enum('unit_change','specialty_change','job_application','career_wish') NOT NULL,
  `target_unit_id` int unsigned DEFAULT NULL,
  `target_job_role_id` int unsigned DEFAULT NULL,
  `target_label` varchar(200) DEFAULT NULL,
  `motivation` text,
  `status` enum('pending','approved','rejected','cancelled','applied') NOT NULL DEFAULT 'pending',
  `requested_by` int unsigned DEFAULT NULL,
  `reviewed_by` int unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `resolution_note` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mobility_tenant_status` (`tenant_id`,`status`,`created_at`),
  KEY `idx_mobility_user` (`tenant_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `personnel_succession_entries` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `target_role_label` varchar(120) NOT NULL,
  `target_job_role_id` int unsigned DEFAULT NULL,
  `readiness` enum('ready_now','ready_3m','develop') NOT NULL DEFAULT 'develop',
  `notes` text,
  `assessed_by` int unsigned DEFAULT NULL,
  `assessed_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_succession_tenant_role` (`tenant_id`,`target_role_label`,`readiness`),
  KEY `idx_succession_user` (`tenant_id`,`user_id`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
