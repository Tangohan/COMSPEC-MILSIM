SET NAMES utf8mb4;

-- ============================================================
-- Journalisation renforcée (tenant + formateur)
-- Complément du modèle competency progression
-- ============================================================

CREATE TABLE IF NOT EXISTS `tenant_training_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `actor_user_id` int unsigned DEFAULT NULL,
  `actor_role_id` int unsigned DEFAULT NULL,
  `event_scope` enum('FRAMEWORK','TENANT_MODULE','RECURRENCE','ROLE_REQUIREMENT','CERTIFICATION') NOT NULL,
  `event_type` varchar(80) NOT NULL,
  `entity_type` varchar(80) NOT NULL,
  `entity_id` int unsigned DEFAULT NULL,
  `old_payload` json DEFAULT NULL,
  `new_payload` json DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_training_logs_tenant_scope` (`tenant_id`,`event_scope`,`created_at`),
  KEY `idx_tenant_training_logs_actor` (`actor_user_id`,`created_at`),
  KEY `idx_tenant_training_logs_entity` (`entity_type`,`entity_id`),
  CONSTRAINT `tenant_training_logs_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tenant_training_logs_actor_user_fk` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tenant_training_logs_actor_role_fk` FOREIGN KEY (`actor_role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `trainer_validation_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `instructor_user_id` int unsigned NOT NULL,
  `target_user_id` int unsigned NOT NULL,
  `module_id` int unsigned NOT NULL,
  `evaluation_id` int unsigned DEFAULT NULL,
  `user_progress_id` int unsigned DEFAULT NULL,
  `action_type` enum('VALIDATION_GRANTED','VALIDATION_REJECTED','FIELD_OBSERVATION','SCORING_OVERRIDE','RECERTIFICATION_REQUIRED') NOT NULL,
  `status_before` enum('NOT_STARTED','IN_PROGRESS','COMPLETED','FAILED','EXPIRED') DEFAULT NULL,
  `status_after` enum('NOT_STARTED','IN_PROGRESS','COMPLETED','FAILED','EXPIRED') DEFAULT NULL,
  `score_before` decimal(5,2) DEFAULT NULL,
  `score_after` decimal(5,2) DEFAULT NULL,
  `note` text,
  `observation_payload` json DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_trainer_validation_tenant` (`tenant_id`,`created_at`),
  KEY `idx_trainer_validation_instructor` (`instructor_user_id`,`created_at`),
  KEY `idx_trainer_validation_target` (`target_user_id`,`created_at`),
  KEY `idx_trainer_validation_module` (`module_id`,`action_type`),
  CONSTRAINT `trainer_validation_logs_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `trainer_validation_logs_instructor_fk` FOREIGN KEY (`instructor_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `trainer_validation_logs_target_fk` FOREIGN KEY (`target_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `trainer_validation_logs_module_fk` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `trainer_validation_logs_evaluation_fk` FOREIGN KEY (`evaluation_id`) REFERENCES `evaluations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `trainer_validation_logs_progress_fk` FOREIGN KEY (`user_progress_id`) REFERENCES `user_progress` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_progress_event_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `module_id` int unsigned NOT NULL,
  `user_progress_id` int unsigned DEFAULT NULL,
  `event_type` enum('STATUS_CHANGED','RECURRENCE_SCHEDULED','RECURRENCE_DUE','RECERTIFICATION_ASSIGNED','AUTO_EXPIRED') NOT NULL,
  `status_before` enum('NOT_STARTED','IN_PROGRESS','COMPLETED','FAILED','EXPIRED') DEFAULT NULL,
  `status_after` enum('NOT_STARTED','IN_PROGRESS','COMPLETED','FAILED','EXPIRED') DEFAULT NULL,
  `expires_at_before` datetime DEFAULT NULL,
  `expires_at_after` datetime DEFAULT NULL,
  `source` enum('SYSTEM','INSTRUCTOR','COMMAND') NOT NULL DEFAULT 'SYSTEM',
  `source_user_id` int unsigned DEFAULT NULL,
  `event_payload` json DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_progress_event_tenant` (`tenant_id`,`created_at`),
  KEY `idx_progress_event_user` (`user_id`,`created_at`),
  KEY `idx_progress_event_module` (`module_id`,`event_type`),
  KEY `idx_progress_event_source` (`source`,`source_user_id`),
  CONSTRAINT `progress_event_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `progress_event_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `progress_event_module_fk` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `progress_event_progress_fk` FOREIGN KEY (`user_progress_id`) REFERENCES `user_progress` (`id`) ON DELETE SET NULL,
  CONSTRAINT `progress_event_source_user_fk` FOREIGN KEY (`source_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
