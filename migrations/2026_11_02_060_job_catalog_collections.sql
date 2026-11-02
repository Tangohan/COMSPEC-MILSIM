-- Emplois : catalogues de communauté (univers, unité fictive, PMC…) et leurs emplois, à côté des catalogues
-- officiels par pays de la fenêtre « Chercher dans le catalogue ». Aucune donnée existante n'est modifiée.
-- Exécuté par database/steps/3480_job_catalog_collections.php ; idempotent (CREATE TABLE IF NOT EXISTS).

CREATE TABLE IF NOT EXISTS `job_catalog_collections` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `name` varchar(80) NOT NULL,
  `description` varchar(1000) DEFAULT NULL,
  `icon` varchar(40) DEFAULT NULL COMMENT 'Icône de la bibliothèque du site (JobRoleIconLibrary)',
  `color` char(7) DEFAULT NULL COMMENT 'Couleur #rrggbb',
  `emblem_path` varchar(500) DEFAULT NULL COMMENT 'Emblème envoyé (uploads/community-media/…)',
  `banner_path` varchar(500) DEFAULT NULL COMMENT 'Bannière envoyée (uploads/community-media/…)',
  `is_public` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = visible en lecture seule par les autres communautés',
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_jcc_tenant_name` (`tenant_id`, `name`),
  KEY `idx_jcc_public` (`is_public`),
  CONSTRAINT `fk_jcc_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `job_catalog_collection_entries` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `collection_id` int unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `abbreviation` varchar(8) DEFAULT NULL,
  `category` varchar(80) DEFAULT NULL COMMENT 'Catégorie libre (armée, faction, département…)',
  `unit` varchar(120) DEFAULT NULL,
  `type` varchar(20) NOT NULL DEFAULT 'operator' COMMENT 'operator, command, support, aviation',
  `description` varchar(1500) DEFAULT NULL,
  `qualifications_json` text DEFAULT NULL COMMENT 'Liste de qualifications ou brevets typiques',
  `icon` varchar(40) DEFAULT NULL,
  `tags_json` text DEFAULT NULL,
  `image_path` varchar(500) DEFAULT NULL COMMENT 'Image envoyée (uploads/community-media/…)',
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_jcce_collection_name` (`collection_id`, `name`),
  KEY `idx_jcce_tenant` (`tenant_id`),
  CONSTRAINT `fk_jcce_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_jcce_collection` FOREIGN KEY (`collection_id`) REFERENCES `job_catalog_collections` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
