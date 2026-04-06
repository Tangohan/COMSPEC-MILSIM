-- Étend lesson_type : quiz, modals, video_embed, video_integrated, slideshow
-- Exécuter une fois sur les bases existantes (MySQL 8+).

SET NAMES utf8mb4;

ALTER TABLE `training_lessons`
  MODIFY COLUMN `lesson_type` ENUM(
    'richtext',
    'video',
    'pdf',
    'audio',
    'scorm_like',
    'checklist',
    'external_link',
    'canvas',
    'quiz',
    'modals',
    'video_embed',
    'video_integrated',
    'slideshow'
  ) NOT NULL DEFAULT 'richtext';
