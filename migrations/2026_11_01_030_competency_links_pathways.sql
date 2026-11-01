-- Référentiel des compétences : explication, mode de validation, liens vers les autres modules
-- (Academy, qualifications, emplois, dotation) et parcours de compétence enregistrés par les formateurs.
-- Exécuté par database/steps/3340_competency_links_pathways.php ; idempotent (CREATE TABLE IF NOT EXISTS).

CREATE TABLE IF NOT EXISTS `competency_grade_requirements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `palier` varchar(120) NOT NULL,
  `palier_order` int NOT NULL DEFAULT 0,
  `label` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `validation_mode` varchar(30) DEFAULT NULL,
  `grade_id` bigint unsigned DEFAULT NULL,
  `acquisition_level` varchar(30) DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT 0,
  `created_by_user_id` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cgr_tenant_order` (`tenant_id`, `palier_order`, `sort_order`),
  KEY `idx_cgr_grade` (`grade_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `competency_requirement_links` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `requirement_id` bigint unsigned NOT NULL COMMENT 'competency_grade_requirements.id',
  `target_type` varchar(32) NOT NULL COMMENT 'academy_course | training_course | module | qualification | job_role | equipment',
  `target_ref` varchar(64) NOT NULL COMMENT 'Identifiant ou clé de l’élément lié',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_crl_requirement_target` (`requirement_id`, `target_type`, `target_ref`),
  KEY `idx_crl_tenant_target` (`tenant_id`, `target_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `competency_pathways` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `name` varchar(150) NOT NULL,
  `summary` varchar(500) DEFAULT NULL,
  `target_kind` varchar(20) NOT NULL COMMENT 'competency | job_role | template',
  `target_ref` varchar(64) NOT NULL,
  `steps_json` longtext NOT NULL,
  `created_by_user_id` int unsigned DEFAULT NULL,
  `updated_by_user_id` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cpw_tenant_target` (`tenant_id`, `target_kind`, `target_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
