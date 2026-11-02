-- Doctrine › Référentiel : personnalisation par communauté du catalogue des publications de référence.
-- Références ajoutées par la communauté, références du site masquées (jamais supprimées), catégories propres et
-- rattachements d'une référence à un document de doctrine. Aucune donnée existante n'est modifiée.
-- Exécuté par database/steps/3560_doctrine_library_custom.php ; idempotent (CREATE TABLE IF NOT EXISTS).

CREATE TABLE IF NOT EXISTS `doctrine_library_categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `label` varchar(80) NOT NULL,
  `color` char(7) DEFAULT NULL COMMENT 'Couleur #rrggbb',
  `archived_at` datetime DEFAULT NULL COMMENT 'NULL = catégorie active',
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_dlc_tenant_label` (`tenant_id`, `label`),
  CONSTRAINT `fk_dlc_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `doctrine_library_entries` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `organization` varchar(120) NOT NULL COMMENT 'Organisation émettrice, réelle ou fictive',
  `code` varchar(64) NOT NULL,
  `title` varchar(255) NOT NULL COMMENT 'Titre dans la langue d’origine',
  `title_translation` varchar(255) DEFAULT NULL,
  `lang` char(2) NOT NULL DEFAULT 'fr',
  `domain` varchar(40) DEFAULT NULL COMMENT 'Domaine du catalogue du site (DoctrineLibraryCatalog::DOMAINS)',
  `category_id` int unsigned DEFAULT NULL COMMENT 'Catégorie de la communauté (prioritaire sur domain)',
  `type` varchar(20) NOT NULL DEFAULT 'doctrine',
  `level` varchar(20) NOT NULL DEFAULT 'tactical',
  `summary` varchar(2000) DEFAULT NULL,
  `links_json` text DEFAULT NULL COMMENT 'Liens https [{label, url}], trois au plus',
  `file_path` varchar(500) DEFAULT NULL COMMENT 'Fichier déposé (storage/uploads/doctrine-library/…)',
  `file_name` varchar(255) DEFAULT NULL,
  `archived_at` datetime DEFAULT NULL COMMENT 'NULL = référence active',
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_dle_tenant_org_code` (`tenant_id`, `organization`, `code`),
  KEY `idx_dle_category` (`category_id`),
  CONSTRAINT `fk_dle_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `doctrine_library_hidden` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `entry_key` varchar(80) NOT NULL COMMENT 'Clé d’une référence du catalogue du site',
  `hidden` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0 = de nouveau affichée',
  `updated_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_dlh_tenant_entry` (`tenant_id`, `entry_key`),
  CONSTRAINT `fk_dlh_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `doctrine_library_links` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `entry_key` varchar(80) NOT NULL COMMENT 'Clé du catalogue du site ou custom-<id>',
  `document_id` int unsigned NOT NULL COMMENT 'Document de doctrine de la communauté',
  `linked_by` int unsigned DEFAULT NULL,
  `unlinked_at` datetime DEFAULT NULL COMMENT 'NULL = rattachement en cours',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_dll_tenant_entry_document` (`tenant_id`, `entry_key`, `document_id`),
  KEY `idx_dll_document` (`document_id`),
  CONSTRAINT `fk_dll_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
