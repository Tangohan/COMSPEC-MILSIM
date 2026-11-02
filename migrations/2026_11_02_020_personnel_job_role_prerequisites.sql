-- Emplois : prérequis (doctrines, heures de jeu / de vol, qualifications, compétences) et dérogations motivées.
-- Aucune donnée existante n'est modifiée : un emploi sans ligne ici n'a aucun prérequis.
-- Exécuté par database/steps/3385_personnel_job_role_prerequisites.php ; idempotent (CREATE TABLE IF NOT EXISTS).

CREATE TABLE IF NOT EXISTS `personnel_job_role_requirements` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `job_role_id` int unsigned NOT NULL,
  `criteria_json` text NOT NULL COMMENT 'Liste de critères (App\Services\Personnel\JobPrerequisiteService)',
  `updated_by` int unsigned DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pjrq_tenant_role` (`tenant_id`, `job_role_id`),
  KEY `idx_pjrq_role` (`job_role_id`),
  CONSTRAINT `fk_pjrq_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pjrq_role` FOREIGN KEY (`job_role_id`) REFERENCES `personnel_job_roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `personnel_job_role_waivers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `job_role_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `reason` varchar(500) NOT NULL,
  `missing_json` text DEFAULT NULL COMMENT 'Prérequis manquants au moment de la dérogation',
  `granted_by` int unsigned DEFAULT NULL,
  `granted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `revoked_by` int unsigned DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `revoke_reason` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pjrw_tenant_user` (`tenant_id`, `user_id`, `revoked_at`),
  KEY `idx_pjrw_tenant_role` (`tenant_id`, `job_role_id`),
  KEY `idx_pjrw_role` (`job_role_id`),
  CONSTRAINT `fk_pjrw_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pjrw_role` FOREIGN KEY (`job_role_id`) REFERENCES `personnel_job_roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
