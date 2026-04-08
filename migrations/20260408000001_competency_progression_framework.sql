SET NAMES utf8mb4;

-- ============================================================
-- Cadre de progression compétences / modules (multi-tenant)
-- Compatible avec les tables existantes : tenants, users, roles, certifications
-- ============================================================

CREATE TABLE IF NOT EXISTS `competency_frameworks` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `code` varchar(64) NOT NULL,
  `name` varchar(160) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_framework_tenant_code` (`tenant_id`,`code`),
  KEY `idx_framework_tenant_active` (`tenant_id`,`is_active`),
  CONSTRAINT `framework_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `competency_levels` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `framework_id` int unsigned NOT NULL,
  `code` varchar(64) NOT NULL,
  `name` varchar(120) NOT NULL,
  `sort_order` int NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_level_framework_code` (`framework_id`,`code`),
  KEY `idx_level_framework_order` (`framework_id`,`sort_order`),
  CONSTRAINT `level_framework_fk` FOREIGN KEY (`framework_id`) REFERENCES `competency_frameworks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `competency_domains` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `framework_id` int unsigned NOT NULL,
  `level_id` int unsigned NOT NULL,
  `code` varchar(64) NOT NULL,
  `name` varchar(120) NOT NULL,
  `sort_order` int NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_domain_level_code` (`level_id`,`code`),
  KEY `idx_domain_framework` (`framework_id`),
  KEY `idx_domain_level_order` (`level_id`,`sort_order`),
  CONSTRAINT `domain_framework_fk` FOREIGN KEY (`framework_id`) REFERENCES `competency_frameworks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `domain_level_fk` FOREIGN KEY (`level_id`) REFERENCES `competency_levels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `competencies` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `framework_id` int unsigned NOT NULL,
  `level_id` int unsigned NOT NULL,
  `domain_id` int unsigned NOT NULL,
  `parent_competency_id` int unsigned DEFAULT NULL,
  `code` varchar(80) NOT NULL,
  `name` varchar(160) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_competency_domain_code` (`domain_id`,`code`),
  KEY `idx_competency_framework` (`framework_id`),
  KEY `idx_competency_parent` (`parent_competency_id`),
  KEY `idx_competency_active` (`is_active`),
  CONSTRAINT `competency_framework_fk` FOREIGN KEY (`framework_id`) REFERENCES `competency_frameworks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `competency_level_fk` FOREIGN KEY (`level_id`) REFERENCES `competency_levels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `competency_domain_fk` FOREIGN KEY (`domain_id`) REFERENCES `competency_domains` (`id`) ON DELETE CASCADE,
  CONSTRAINT `competency_parent_fk` FOREIGN KEY (`parent_competency_id`) REFERENCES `competencies` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `knowledge_units` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `competency_id` int unsigned NOT NULL,
  `code` varchar(80) NOT NULL,
  `name` varchar(160) NOT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT 0,
  `is_critical` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_knowledge_competency_code` (`competency_id`,`code`),
  KEY `idx_knowledge_competency_order` (`competency_id`,`sort_order`),
  CONSTRAINT `knowledge_competency_fk` FOREIGN KEY (`competency_id`) REFERENCES `competencies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `modules` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `framework_id` int unsigned DEFAULT NULL,
  `code` varchar(80) NOT NULL,
  `name` varchar(180) NOT NULL,
  `module_type` enum('ALPHA','BRAVO','CHARLIE','DELTA') NOT NULL,
  `delivery_mode` enum('INITIAL','RENFORCE','RECYCLAGE','CRITIQUE') NOT NULL DEFAULT 'INITIAL',
  `description` text DEFAULT NULL,
  `duration_min` int unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_mandatory_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_module_tenant_code` (`tenant_id`,`code`),
  KEY `idx_module_tenant_type` (`tenant_id`,`module_type`),
  KEY `idx_module_framework` (`framework_id`),
  KEY `idx_module_active` (`is_active`),
  CONSTRAINT `module_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `module_framework_fk` FOREIGN KEY (`framework_id`) REFERENCES `competency_frameworks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `module_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `module_knowledge` (
  `module_id` int unsigned NOT NULL,
  `knowledge_id` int unsigned NOT NULL,
  PRIMARY KEY (`module_id`,`knowledge_id`),
  KEY `idx_module_knowledge_knowledge` (`knowledge_id`),
  CONSTRAINT `module_knowledge_module_fk` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `module_knowledge_unit_fk` FOREIGN KEY (`knowledge_id`) REFERENCES `knowledge_units` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `module_sequences` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `framework_id` int unsigned NOT NULL,
  `module_id` int unsigned NOT NULL,
  `sequence_order` int NOT NULL,
  `phase_label` varchar(120) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_module_sequence_framework_module` (`framework_id`,`module_id`),
  UNIQUE KEY `uk_module_sequence_framework_order` (`framework_id`,`sequence_order`),
  CONSTRAINT `module_sequence_framework_fk` FOREIGN KEY (`framework_id`) REFERENCES `competency_frameworks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `module_sequence_module_fk` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `module_dependencies` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `module_id` int unsigned NOT NULL,
  `requires_module_id` int unsigned NOT NULL,
  `dependency_type` enum('PREREQUIS','RENFORCEMENT','RECYCLAGE') NOT NULL DEFAULT 'PREREQUIS',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_module_dep_pair` (`module_id`,`requires_module_id`,`dependency_type`),
  KEY `idx_module_dep_requires` (`requires_module_id`),
  CONSTRAINT `module_dep_module_fk` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `module_dep_requires_fk` FOREIGN KEY (`requires_module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `recurrence_rules` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `module_id` int unsigned NOT NULL,
  `recurrence_type` enum('NONE','PERIODIC','EVENT_BASED') NOT NULL DEFAULT 'NONE',
  `interval_days` int unsigned DEFAULT NULL,
  `mandatory` tinyint(1) NOT NULL DEFAULT 0,
  `grace_days` int unsigned DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_recurrence_module` (`module_id`),
  CONSTRAINT `recurrence_module_fk` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tenant_modules` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `module_id` int unsigned NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_mandatory` tinyint(1) NOT NULL DEFAULT 0,
  `custom_order` int DEFAULT NULL,
  `recurrence_override_type` enum('NONE','PERIODIC','EVENT_BASED') DEFAULT NULL,
  `recurrence_override_days` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_module` (`tenant_id`,`module_id`),
  KEY `idx_tenant_module_active` (`tenant_id`,`is_active`),
  CONSTRAINT `tenant_modules_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tenant_modules_module_fk` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `module_competencies` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `module_id` int unsigned NOT NULL,
  `competency_id` int unsigned NOT NULL,
  `weight` decimal(5,2) NOT NULL DEFAULT 1.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_module_competency` (`module_id`,`competency_id`),
  KEY `idx_module_competency_competency` (`competency_id`),
  CONSTRAINT `module_competencies_module_fk` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `module_competencies_competency_fk` FOREIGN KEY (`competency_id`) REFERENCES `competencies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `evaluations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `module_id` int unsigned NOT NULL,
  `evaluation_type` enum('QCM','SCENARIO','FIELD') NOT NULL,
  `name` varchar(160) NOT NULL,
  `passing_score` decimal(5,2) DEFAULT NULL,
  `max_score` decimal(5,2) DEFAULT NULL,
  `requires_validator` tinyint(1) NOT NULL DEFAULT 0,
  `validator_role_id` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_evaluation_module_type` (`module_id`,`evaluation_type`),
  CONSTRAINT `evaluation_module_fk` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `evaluation_validator_role_fk` FOREIGN KEY (`validator_role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_progress` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `module_id` int unsigned NOT NULL,
  `status` enum('NOT_STARTED','IN_PROGRESS','COMPLETED','FAILED','EXPIRED') NOT NULL DEFAULT 'NOT_STARTED',
  `score` decimal(5,2) DEFAULT NULL,
  `attempts` int unsigned NOT NULL DEFAULT 0,
  `validated_by` int unsigned DEFAULT NULL,
  `validated_at` datetime DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `last_activity_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_progress` (`user_id`,`module_id`),
  KEY `idx_user_progress_tenant_status` (`tenant_id`,`status`),
  KEY `idx_user_progress_expiry` (`expires_at`),
  CONSTRAINT `user_progress_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_progress_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_progress_module_fk` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_progress_validator_fk` FOREIGN KEY (`validated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `role_requirements` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `role_id` int unsigned NOT NULL,
  `required_module_id` int unsigned DEFAULT NULL,
  `required_certification_id` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_role_requirement_unique` (`role_id`,`required_module_id`,`required_certification_id`),
  KEY `idx_role_requirement_module` (`required_module_id`),
  KEY `idx_role_requirement_certification` (`required_certification_id`),
  CONSTRAINT `role_requirements_role_fk` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_requirements_module_fk` FOREIGN KEY (`required_module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_requirements_certification_fk` FOREIGN KEY (`required_certification_id`) REFERENCES `certifications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `certification_modules` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `certification_id` int unsigned NOT NULL,
  `module_id` int unsigned NOT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT 1,
  `minimum_score` decimal(5,2) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_certification_module` (`certification_id`,`module_id`),
  KEY `idx_certification_modules_module` (`module_id`),
  CONSTRAINT `certification_modules_cert_fk` FOREIGN KEY (`certification_id`) REFERENCES `certifications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `certification_modules_module_fk` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
