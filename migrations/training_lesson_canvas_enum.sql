-- Ajoute le type de leçon « canvas » (parcours slides / modales / médias) — exécuter une fois sur les bases existantes.
-- MySQL 8+ : étendre l’ENUM lesson_type.

ALTER TABLE `training_lessons`
  MODIFY COLUMN `lesson_type` ENUM(
    'richtext',
    'video',
    'pdf',
    'audio',
    'scorm_like',
    'checklist',
    'external_link',
    'canvas'
  ) NOT NULL DEFAULT 'richtext';
