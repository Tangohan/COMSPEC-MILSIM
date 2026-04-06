-- Versions Studio LMS enregistrées sur chaque formation (création + dernier enregistrement).
-- Exécution idempotente : voir bootstrap/training_course_lms_platform_version_migration.php

ALTER TABLE `training_courses`
  ADD COLUMN `lms_created_with_version` VARCHAR(32) NULL DEFAULT NULL,
  ADD COLUMN `lms_last_saved_with_version` VARCHAR(32) NULL DEFAULT NULL;
