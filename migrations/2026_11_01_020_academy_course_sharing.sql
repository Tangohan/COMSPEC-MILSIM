-- Academy Studio : partage d'une formation (personnes et lien), façon Google Docs.
-- Le jeton du lien n'est jamais stocké en clair : empreinte SHA-256 pour l'ouverture, copie chiffrée (APP_KEY)
-- pour « Copier le lien ». Exécuté par database/steps/3270_academy_course_lifecycle_sharing.php ; idempotent.

CREATE TABLE IF NOT EXISTS `academy_course_shares` (
  `tenant_id` bigint unsigned NOT NULL,
  `course_key` varchar(64) NOT NULL,
  `user_id` bigint unsigned NOT NULL COMMENT 'Membre de la communauté invité',
  `role` varchar(16) NOT NULL COMMENT 'viewer | editor',
  `granted_by` bigint unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`tenant_id`, `course_key`, `user_id`),
  KEY `idx_academy_shares_user` (`tenant_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `academy_course_links` (
  `tenant_id` bigint unsigned NOT NULL,
  `course_key` varchar(64) NOT NULL,
  `token_hash` char(64) NOT NULL COMMENT 'SHA-256 du jeton',
  `token_cipher` text DEFAULT NULL COMMENT 'Jeton chiffré, pour recopier le lien',
  `access` varchar(16) NOT NULL COMMENT 'community | public',
  `role` varchar(16) NOT NULL COMMENT 'viewer | editor (editor : communauté seulement)',
  `expires_at` datetime DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`tenant_id`, `course_key`),
  UNIQUE KEY `uk_academy_links_token` (`token_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
