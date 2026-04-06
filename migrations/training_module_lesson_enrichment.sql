-- Enrichissement modules / leçons LMS (métadonnées pédagogiques).
-- Appliqué aussi via bootstrap/training_module_lesson_enrichment_migration.php sur les bases existantes.

ALTER TABLE `training_modules`
    ADD COLUMN `subtitle` VARCHAR(255) NULL DEFAULT NULL AFTER `description`,
    ADD COLUMN `learning_objectives` TEXT NULL DEFAULT NULL AFTER `subtitle`,
    ADD COLUMN `estimated_minutes` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `learning_objectives`;

ALTER TABLE `training_lessons`
    ADD COLUMN `summary` VARCHAR(500) NULL DEFAULT NULL AFTER `title`,
    ADD COLUMN `learning_objectives` TEXT NULL DEFAULT NULL AFTER `summary`,
    ADD COLUMN `difficulty` VARCHAR(20) NULL DEFAULT NULL AFTER `duration_minutes`,
    ADD COLUMN `instructor_notes` TEXT NULL DEFAULT NULL AFTER `learning_objectives`;
