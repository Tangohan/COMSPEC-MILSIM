-- Colonnes LMS optionnelles sur training_courses (voir bootstrap/training_course_lms_theme_migration.php pour exécution idempotente).

ALTER TABLE `training_courses`
    ADD COLUMN `course_code` VARCHAR(32) NULL DEFAULT NULL AFTER `slug`,
    ADD COLUMN `learning_objectives` LONGTEXT NULL DEFAULT NULL AFTER `description`,
    ADD COLUMN `theme_json` LONGTEXT NULL DEFAULT NULL AFTER `learning_objectives`;
