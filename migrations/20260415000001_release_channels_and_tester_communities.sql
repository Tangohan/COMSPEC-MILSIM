-- Découplage strict : produits déployables / canaux / communautés de test.
-- Tables préfixées platform_* pour éviter tout conflit avec `modules` (parcours pédagogiques LMS, int unsigned).
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `platform_modules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(120) NOT NULL,
  `name` varchar(180) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_public` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_platform_modules_code` (`code`),
  KEY `idx_platform_modules_visibility` (`is_active`,`is_public`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `platform_module_versions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `module_id` bigint unsigned NOT NULL,
  `version` varchar(80) NOT NULL,
  `build_ref` varchar(190) DEFAULT NULL,
  `changelog` longtext DEFAULT NULL,
  `artifact_path` varchar(500) DEFAULT NULL,
  `commit_hash` varchar(80) DEFAULT NULL,
  `status` enum('draft','validated','published','rollback_ready','deprecated') NOT NULL DEFAULT 'draft',
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_platform_module_version` (`module_id`,`version`),
  KEY `idx_platform_module_versions_status` (`module_id`,`status`,`created_at`),
  CONSTRAINT `fk_platform_module_versions_module` FOREIGN KEY (`module_id`) REFERENCES `platform_modules` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_platform_module_versions_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `deployment_channels` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(24) NOT NULL,
  `name` varchar(80) NOT NULL,
  `priority` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_deployment_channels_code` (`code`),
  KEY `idx_deployment_channels_priority` (`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `platform_module_channel_releases` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `module_id` bigint unsigned NOT NULL,
  `module_version_id` bigint unsigned NOT NULL,
  `channel_id` int unsigned NOT NULL,
  `is_current` tinyint(1) NOT NULL DEFAULT 0,
  `current_release_guard` tinyint GENERATED ALWAYS AS (CASE WHEN `is_current` = 1 THEN 1 ELSE NULL END) STORED,
  `deployed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deployed_by` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_platform_module_channel_current` (`module_id`,`channel_id`,`current_release_guard`),
  KEY `idx_platform_module_channel_releases_lookup` (`module_id`,`channel_id`,`deployed_at`),
  CONSTRAINT `fk_platform_mcr_module` FOREIGN KEY (`module_id`) REFERENCES `platform_modules` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_platform_mcr_version` FOREIGN KEY (`module_version_id`) REFERENCES `platform_module_versions` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_platform_mcr_channel` FOREIGN KEY (`channel_id`) REFERENCES `deployment_channels` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_platform_mcr_by` FOREIGN KEY (`deployed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tester_communities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(120) NOT NULL,
  `name` varchar(180) NOT NULL,
  `description` text DEFAULT NULL,
  `owner_user_id` int unsigned DEFAULT NULL,
  `access_rules` json DEFAULT NULL,
  `valid_from` datetime DEFAULT NULL,
  `valid_until` datetime DEFAULT NULL,
  `priority` int NOT NULL DEFAULT 100,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `visibility` enum('private','internal','restricted') NOT NULL DEFAULT 'internal',
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_tester_communities_code` (`code`),
  KEY `idx_tester_communities_active` (`is_active`,`priority`),
  CONSTRAINT `fk_tester_communities_owner` FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_tester_communities_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tester_community_members` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `community_id` bigint unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `joined_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime DEFAULT NULL,
  `status` enum('active','suspended','expired') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_tester_community_member` (`community_id`,`user_id`),
  KEY `idx_tester_community_members_lookup` (`user_id`,`status`,`expires_at`),
  CONSTRAINT `fk_tester_community_members_community` FOREIGN KEY (`community_id`) REFERENCES `tester_communities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tester_community_members_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `platform_module_access_rules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `module_id` bigint unsigned NOT NULL,
  `rule_type` enum('public','deny_all','allow_community','deny_community') NOT NULL,
  `community_id` bigint unsigned DEFAULT NULL,
  `applies_to_version_id` bigint unsigned DEFAULT NULL,
  `environment_channel_id` int unsigned DEFAULT NULL,
  `priority` int NOT NULL DEFAULT 100,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_platform_module_access_rules_lookup` (`module_id`,`is_active`,`priority`),
  CONSTRAINT `fk_platform_mar_module` FOREIGN KEY (`module_id`) REFERENCES `platform_modules` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_platform_mar_community` FOREIGN KEY (`community_id`) REFERENCES `tester_communities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_platform_mar_version` FOREIGN KEY (`applies_to_version_id`) REFERENCES `platform_module_versions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_platform_mar_channel` FOREIGN KEY (`environment_channel_id`) REFERENCES `deployment_channels` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `platform_feature_flags` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `module_id` bigint unsigned NOT NULL,
  `code` varchar(120) NOT NULL,
  `name` varchar(180) NOT NULL,
  `description` text DEFAULT NULL,
  `default_state` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_platform_feature_flags_module_code` (`module_id`,`code`),
  CONSTRAINT `fk_platform_feature_flags_module` FOREIGN KEY (`module_id`) REFERENCES `platform_modules` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `platform_feature_flag_rules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `feature_flag_id` bigint unsigned NOT NULL,
  `rule_type` enum('allow_community','deny_community','allow_user','deny_user') NOT NULL,
  `community_id` bigint unsigned DEFAULT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `state` tinyint(1) NOT NULL DEFAULT 1,
  `priority` int NOT NULL DEFAULT 100,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_platform_feature_flag_rules_priority` (`feature_flag_id`,`priority`,`is_active`),
  CONSTRAINT `fk_platform_ffr_flag` FOREIGN KEY (`feature_flag_id`) REFERENCES `platform_feature_flags` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_platform_ffr_community` FOREIGN KEY (`community_id`) REFERENCES `tester_communities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_platform_ffr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `deployment_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `module_version_id` bigint unsigned NOT NULL,
  `target_channel_id` int unsigned NOT NULL,
  `status` enum('queued','running','success','failed','rolled_back') NOT NULL DEFAULT 'queued',
  `started_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  `triggered_by` int unsigned DEFAULT NULL,
  `log_path` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_deployment_jobs_status` (`status`,`target_channel_id`,`started_at`),
  CONSTRAINT `fk_deployment_jobs_version` FOREIGN KEY (`module_version_id`) REFERENCES `platform_module_versions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_deployment_jobs_channel` FOREIGN KEY (`target_channel_id`) REFERENCES `deployment_channels` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_deployment_jobs_triggered_by` FOREIGN KEY (`triggered_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tester_feedback` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `module_id` bigint unsigned NOT NULL,
  `module_version_id` bigint unsigned NOT NULL,
  `feature_flag_id` bigint unsigned DEFAULT NULL,
  `community_id` bigint unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `type` enum('bug','ui','ux','idea','regression','performance') NOT NULL,
  `severity` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `title` varchar(255) NOT NULL,
  `description` longtext DEFAULT NULL,
  `steps_to_reproduce` longtext DEFAULT NULL,
  `expected_result` longtext DEFAULT NULL,
  `actual_result` longtext DEFAULT NULL,
  `device_info` varchar(255) DEFAULT NULL,
  `browser_info` varchar(255) DEFAULT NULL,
  `screenshots_json` json DEFAULT NULL,
  `status` enum('new','triaged','in_progress','fixed','rejected','closed') NOT NULL DEFAULT 'new',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tester_feedback_filters` (`module_id`,`module_version_id`,`community_id`,`severity`,`type`,`status`),
  CONSTRAINT `fk_tester_feedback_module` FOREIGN KEY (`module_id`) REFERENCES `platform_modules` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tester_feedback_version` FOREIGN KEY (`module_version_id`) REFERENCES `platform_module_versions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tester_feedback_flag` FOREIGN KEY (`feature_flag_id`) REFERENCES `platform_feature_flags` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_tester_feedback_community` FOREIGN KEY (`community_id`) REFERENCES `tester_communities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tester_feedback_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `deployment_audit_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `actor_user_id` int unsigned DEFAULT NULL,
  `action` varchar(120) NOT NULL,
  `entity_type` varchar(80) NOT NULL,
  `entity_id` bigint unsigned NOT NULL,
  `old_value_json` json DEFAULT NULL,
  `new_value_json` json DEFAULT NULL,
  `ip_address` varchar(64) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_deployment_audit_lookup` (`entity_type`,`entity_id`,`created_at`),
  CONSTRAINT `fk_deployment_audit_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `deployment_channels` (`code`, `name`, `priority`) VALUES
  ('DEV', 'Development', 10),
  ('INTERNAL', 'Internal Validation', 20),
  ('TEST', 'Test', 30),
  ('PREPROD', 'Preproduction', 40),
  ('PROD', 'Production', 50)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `priority` = VALUES(`priority`);

INSERT INTO `tester_communities` (`code`, `name`, `description`, `priority`, `is_active`, `visibility`) VALUES
  ('ALPHA_CORE', 'Alpha Core', 'Accès aux modules les plus instables pour validation produit précoce.', 10, 1, 'restricted'),
  ('BETA_UI_UX', 'Bêta UI/UX', 'Accès anticipé aux refontes d\'interfaces et de parcours.', 20, 1, 'internal'),
  ('BETA_FORUM', 'Bêta Forum', 'Validation des nouveautés forum, permissions et modération.', 25, 1, 'internal'),
  ('BETA_FORMATION', 'Bêta Formation', 'Validation des nouveaux parcours pédagogiques et progression.', 30, 1, 'internal'),
  ('BETA_DOCUMENTS', 'Bêta Documents', 'Tests des nouvelles arborescences et droits documentaires.', 35, 1, 'internal'),
  ('BETA_ATAK', 'Bêta ATAK', 'Validation des couches tactiques, synchronisations et overlays.', 40, 1, 'restricted'),
  ('QA_ADMIN', 'QA Admin', 'Réservé aux écrans d\'administration et supervision.', 15, 1, 'restricted'),
  ('REGRESSION_TEAM', 'Regression Team', 'Vérifie qu\'aucune fonctionnalité existante n\'est cassée.', 50, 1, 'internal'),
  ('MOBILE_TESTERS', 'Mobile Testers', 'Contrôle responsive, ergonomie tactile et performances mobiles.', 60, 1, 'internal'),
  ('ACCESSIBILITY_REVIEW', 'Accessibility Review', 'Validation lisibilité, contraste, clavier et cohérence interface.', 70, 1, 'internal'),
  ('TRUSTED_OPERATORS', 'Trusted Operators', 'Test terrain sur parcours opérationnels réels.', 80, 1, 'restricted')
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `description` = VALUES(`description`),
  `priority` = VALUES(`priority`),
  `is_active` = VALUES(`is_active`),
  `visibility` = VALUES(`visibility`);
