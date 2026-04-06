-- LMS engagement : politique d'inscription, audio, créneaux, favoris, avis, questions, commentaires.
-- Exécution idempotente recommandée via bootstrap/training_lms_engagement_migration.php

-- Colonnes training_courses (voir bootstrap pour ALTER conditionnels)
-- enrollment_policy_json, instruction_audio_url, instruction_audio_instructor_optional, instruction_audio_notes

-- Tables : training_course_sessions, training_course_favorites, training_course_reviews,
-- training_course_questions, training_course_comments
-- (DDL complet dans bootstrap/training_lms_engagement_migration.php)
