-- Academy : qui peut faire quoi (créer, modifier, publier, inscrire, valider, délivrer, statistiques) et qui voit chaque cours.
-- Aucune ligne = comportement d'origine (encadrement de la formation pour tout, cours visibles par toute la communauté).
-- Exécuté par database/steps/3520_academy_access_rules.php ; idempotent.

CREATE TABLE IF NOT EXISTS `academy_access_rules` (
  `tenant_id` bigint unsigned NOT NULL,
  `course_key` varchar(64) NOT NULL DEFAULT '' COMMENT 'Vide : règle de la communauté ; sinon public du cours',
  `capability` varchar(24) NOT NULL COMMENT 'create | edit | publish | assign | validate | certify | stats | view',
  `subject_type` varchar(16) NOT NULL COMMENT 'admins (marque « réglé ») | staff | role | job | unit | user',
  `subject_id` bigint unsigned NOT NULL DEFAULT 0,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`tenant_id`, `course_key`, `capability`, `subject_type`, `subject_id`),
  KEY `idx_academy_access_rules_capability` (`tenant_id`, `capability`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `academy_course_settings` (
  `tenant_id` bigint unsigned NOT NULL,
  `course_key` varchar(64) NOT NULL,
  `owner_id` bigint unsigned DEFAULT NULL COMMENT 'Auteur du cours (NULL : cours antérieur, aucun propriétaire)',
  `audience` varchar(16) NOT NULL DEFAULT 'community' COMMENT 'community | restricted',
  `private_draft` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 : brouillon visible de son auteur, des personnes invitées et des administrateurs seulement',
  `updated_by` bigint unsigned DEFAULT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`tenant_id`, `course_key`),
  KEY `idx_academy_course_settings_owner` (`tenant_id`, `owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
