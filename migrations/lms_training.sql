-- LMS Training — Migration
-- 1) Renommage des tables existantes (legacy)
-- 2) Création des nouvelles tables (training_courses, modules, lessons, etc.)
-- Exécuter après schema.sql (ou après les migrations Phinx de base).

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ========== 1. Renommage legacy (si les tables existent) ==========
-- Si installation neuve sans anciennes tables training_*, commenter les 3 lignes ci-dessous.
RENAME TABLE `training_modules` TO `legacy_training_modules`;
RENAME TABLE `training_progress` TO `legacy_training_progress`;
RENAME TABLE `training_certificates` TO `legacy_training_certificates`;

-- ========== 2. Nouvelles tables ==========

-- 2.1 Formations (conteneur principal)
CREATE TABLE `training_courses` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT UNSIGNED NOT NULL,
    `uuid` CHAR(36) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL,
    `short_description` VARCHAR(500) NULL,
    `description` LONGTEXT NULL,
    `thumbnail_path` VARCHAR(255) NULL,
    `banner_path` VARCHAR(255) NULL,
    `category` VARCHAR(100) NULL,
    `level` ENUM('initiation','intermediaire','avance','expert') DEFAULT 'initiation',
    `language_code` VARCHAR(10) DEFAULT 'fr',
    `estimated_minutes` INT UNSIGNED DEFAULT 0,
    `passing_score` DECIMAL(5,2) DEFAULT 80.00,
    `is_mandatory` TINYINT(1) DEFAULT 0,
    `is_certifying` TINYINT(1) DEFAULT 0,
    `validity_days` INT UNSIGNED NULL,
    `visibility` ENUM('draft','private','published','archived') DEFAULT 'draft',
    `created_by` INT UNSIGNED NOT NULL,
    `updated_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_training_courses_uuid` (`uuid`),
    UNIQUE KEY `uk_training_courses_tenant_slug` (`tenant_id`,`slug`),
    INDEX `idx_training_courses_visibility` (`visibility`),
    INDEX `idx_training_courses_category` (`category`),
    INDEX `idx_training_courses_tenant` (`tenant_id`),
    CONSTRAINT `fk_training_courses_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2.2 Modules (blocs d'un parcours)
CREATE TABLE `training_modules` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `course_id` BIGINT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `subtitle` VARCHAR(255) NULL DEFAULT NULL,
    `learning_objectives` TEXT NULL DEFAULT NULL,
    `estimated_minutes` INT UNSIGNED NOT NULL DEFAULT 0,
    `position` INT UNSIGNED NOT NULL DEFAULT 1,
    `is_required` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_training_modules_course_position` (`course_id`, `position`),
    CONSTRAINT `fk_training_modules_course` FOREIGN KEY (`course_id`) REFERENCES `training_courses` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2.3 Leçons
CREATE TABLE `training_lessons` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `module_id` BIGINT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `summary` VARCHAR(500) NULL DEFAULT NULL,
    `learning_objectives` TEXT NULL DEFAULT NULL,
    `instructor_notes` TEXT NULL DEFAULT NULL,
    `lesson_type` ENUM('richtext','video','pdf','audio','scorm_like','checklist','external_link','canvas','quiz','modals','video_embed','video_integrated','slideshow') NOT NULL DEFAULT 'richtext',
    `content` LONGTEXT NULL,
    `external_url` VARCHAR(500) NULL,
    `duration_minutes` INT UNSIGNED DEFAULT 0,
    `difficulty` VARCHAR(20) NULL DEFAULT NULL,
    `position` INT UNSIGNED NOT NULL DEFAULT 1,
    `is_required` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_training_lessons_module_position` (`module_id`, `position`),
    CONSTRAINT `fk_training_lessons_module` FOREIGN KEY (`module_id`) REFERENCES `training_modules` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2.4 Ressources
CREATE TABLE `training_resources` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `lesson_id` BIGINT UNSIGNED NOT NULL,
    `resource_type` ENUM('pdf','image','video','audio','zip','attachment','link') NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(255) NULL,
    `external_url` VARCHAR(500) NULL,
    `mime_type` VARCHAR(100) NULL,
    `file_size` BIGINT UNSIGNED NULL,
    `library_document_id` INT UNSIGNED NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_training_resources_lesson` (`lesson_id`),
    INDEX `idx_training_resources_library_document` (`library_document_id`),
    CONSTRAINT `fk_training_resources_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `training_lessons` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_training_resources_library_document` FOREIGN KEY (`library_document_id`) REFERENCES `documents` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2.5 Assignations / inscriptions
CREATE TABLE `training_enrollments` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT UNSIGNED NOT NULL,
    `course_id` BIGINT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `assigned_by` INT UNSIGNED NULL,
    `assignment_type` ENUM('manual','role','unit','campaign','self_enroll') DEFAULT 'manual',
    `status` ENUM('assigned','in_progress','completed','failed','expired','revoked','pending_approval','withdrawn') DEFAULT 'assigned',
    `assigned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `started_at` DATETIME NULL,
    `completed_at` DATETIME NULL,
    `expires_at` DATETIME NULL,
    `motivation_text` TEXT NULL,
    UNIQUE KEY `uk_training_enrollment` (`course_id`, `user_id`),
    INDEX `idx_training_enrollments_user` (`user_id`),
    INDEX `idx_training_enrollments_status` (`status`),
    INDEX `idx_training_enrollments_tenant` (`tenant_id`),
    CONSTRAINT `fk_training_enrollments_course` FOREIGN KEY (`course_id`) REFERENCES `training_courses` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_training_enrollments_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_training_enrollments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2.6 Progression fine (par leçon)
CREATE TABLE `training_progress` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `enrollment_id` BIGINT UNSIGNED NOT NULL,
    `lesson_id` BIGINT UNSIGNED NOT NULL,
    `status` ENUM('not_started','in_progress','completed','skipped') DEFAULT 'not_started',
    `progress_percent` DECIMAL(5,2) DEFAULT 0.00,
    `time_spent_seconds` INT UNSIGNED DEFAULT 0,
    `last_position_seconds` INT UNSIGNED DEFAULT 0,
    `viewed_at` DATETIME NULL,
    `completed_at` DATETIME NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_training_progress` (`enrollment_id`, `lesson_id`),
    INDEX `idx_training_progress_lesson` (`lesson_id`),
    CONSTRAINT `fk_training_progress_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `training_enrollments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_training_progress_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `training_lessons` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2.7 Quiz
CREATE TABLE `training_quizzes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `module_id` BIGINT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `passing_score` DECIMAL(5,2) DEFAULT 80.00,
    `max_attempts` INT UNSIGNED DEFAULT 3,
    `time_limit_minutes` INT UNSIGNED NULL,
    `randomize_questions` TINYINT(1) DEFAULT 0,
    `is_final_exam` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_training_quizzes_module` FOREIGN KEY (`module_id`) REFERENCES `training_modules` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2.8 Questions
CREATE TABLE `training_quiz_questions` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `quiz_id` BIGINT UNSIGNED NOT NULL,
    `question_type` ENUM('single_choice','multiple_choice','true_false','short_text','long_text') NOT NULL,
    `question_text` LONGTEXT NOT NULL,
    `explanation` LONGTEXT NULL,
    `points` DECIMAL(6,2) DEFAULT 1.00,
    `position` INT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_training_quiz_questions_quiz_position` (`quiz_id`, `position`),
    CONSTRAINT `fk_training_quiz_questions_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `training_quizzes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2.9 Réponses possibles
CREATE TABLE `training_quiz_answers` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `question_id` BIGINT UNSIGNED NOT NULL,
    `answer_text` LONGTEXT NOT NULL,
    `is_correct` TINYINT(1) DEFAULT 0,
    `position` INT UNSIGNED NOT NULL DEFAULT 1,
    CONSTRAINT `fk_training_quiz_answers_question` FOREIGN KEY (`question_id`) REFERENCES `training_quiz_questions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2.10 Tentatives quiz
CREATE TABLE `training_quiz_attempts` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `quiz_id` BIGINT UNSIGNED NOT NULL,
    `enrollment_id` BIGINT UNSIGNED NOT NULL,
    `started_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `submitted_at` DATETIME NULL,
    `score` DECIMAL(6,2) NULL,
    `passed` TINYINT(1) DEFAULT 0,
    `status` ENUM('in_progress','submitted','graded','expired') DEFAULT 'in_progress',
    INDEX `idx_training_quiz_attempts_enrollment` (`enrollment_id`),
    CONSTRAINT `fk_training_quiz_attempts_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `training_quizzes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_training_quiz_attempts_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `training_enrollments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2.11 Réponses utilisateur (quiz)
CREATE TABLE `training_quiz_responses` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `attempt_id` BIGINT UNSIGNED NOT NULL,
    `question_id` BIGINT UNSIGNED NOT NULL,
    `answer_id` BIGINT UNSIGNED NULL,
    `response_text` LONGTEXT NULL,
    `is_correct` TINYINT(1) NULL,
    `points_awarded` DECIMAL(6,2) DEFAULT 0.00,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_training_quiz_responses_attempt` (`attempt_id`),
    CONSTRAINT `fk_training_quiz_responses_attempt` FOREIGN KEY (`attempt_id`) REFERENCES `training_quiz_attempts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_training_quiz_responses_question` FOREIGN KEY (`question_id`) REFERENCES `training_quiz_questions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2.12 Certification (liée à l'enrollment)
CREATE TABLE `training_certificates` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT UNSIGNED NOT NULL,
    `enrollment_id` BIGINT UNSIGNED NOT NULL,
    `certificate_number` VARCHAR(100) NOT NULL,
    `issued_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME NULL,
    `final_score` DECIMAL(6,2) NOT NULL,
    `pdf_path` VARCHAR(255) NULL,
    `status` ENUM('valid','expired','revoked') DEFAULT 'valid',
    UNIQUE KEY `uk_training_certificates_number` (`certificate_number`),
    INDEX `idx_training_certificates_enrollment` (`enrollment_id`),
    CONSTRAINT `fk_training_certificates_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `training_enrollments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_training_certificates_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2.13 Audit training
CREATE TABLE `training_audit_log` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT UNSIGNED NULL,
    `user_id` INT UNSIGNED NULL,
    `action` VARCHAR(100) NOT NULL,
    `target_type` VARCHAR(100) NOT NULL,
    `target_id` BIGINT UNSIGNED NOT NULL,
    `old_value` JSON NULL,
    `new_value` JSON NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` VARCHAR(500) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_training_audit_target` (`target_type`, `target_id`),
    INDEX `idx_training_audit_user` (`user_id`),
    INDEX `idx_training_audit_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
