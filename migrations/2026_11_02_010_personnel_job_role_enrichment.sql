-- Fonctions (emplois) : étiquettes de la communauté et fonctions du site masquées par une communauté.
-- Les colonnes personnel_job_roles.abbreviation / icon et leur index sont ajoutés par l'étape PHP.
-- Exécuté par database/steps/3360_personnel_job_role_enrichment.php ; idempotent (CREATE TABLE IF NOT EXISTS).

CREATE TABLE IF NOT EXISTS `personnel_job_role_tags` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `name` varchar(40) NOT NULL,
  `color` varchar(16) DEFAULT NULL COMMENT 'Teinte de la palette (App\Services\Personnel\JobRoleEnrichmentService::TAG_COLORS)',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pjr_tags_tenant_name` (`tenant_id`, `name`),
  CONSTRAINT `fk_pjr_tags_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `personnel_job_role_tag_links` (
  `tenant_id` int unsigned NOT NULL,
  `job_role_id` int unsigned NOT NULL,
  `tag_id` int unsigned NOT NULL,
  PRIMARY KEY (`job_role_id`, `tag_id`),
  KEY `idx_pjr_tag_links_tenant_tag` (`tenant_id`, `tag_id`),
  CONSTRAINT `fk_pjr_tag_links_role` FOREIGN KEY (`job_role_id`) REFERENCES `personnel_job_roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pjr_tag_links_tag` FOREIGN KEY (`tag_id`) REFERENCES `personnel_job_role_tags` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `personnel_job_role_catalog_hidden` (
  `tenant_id` int unsigned NOT NULL,
  `catalog_slug` varchar(80) NOT NULL COMMENT 'Fonction du référentiel du site (MilitaryOperationalRoleCatalog)',
  `hidden_by` int unsigned DEFAULT NULL,
  `hidden_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tenant_id`, `catalog_slug`),
  CONSTRAINT `fk_pjr_catalog_hidden_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
