-- Référentiel militaire global (SOF / organisations réelles)
-- Distinct de la table ORBAT tenant `units`.
-- Exécution incrémentale via bootstrap/military_referential_migration.php

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `countries` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `iso2` CHAR(2) NOT NULL,
  `iso3` CHAR(3) NOT NULL,
  `name_fr` VARCHAR(120) NOT NULL,
  `name_en` VARCHAR(120) NOT NULL,
  `flag_code` VARCHAR(16) DEFAULT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `countries_iso2_uq` (`iso2`),
  UNIQUE KEY `countries_iso3_uq` (`iso3`),
  KEY `countries_active_idx` (`active`),
  KEY `countries_sort_idx` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `military_services` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `country_id` BIGINT UNSIGNED NOT NULL,
  `code` VARCHAR(64) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `short_name` VARCHAR(120) DEFAULT NULL,
  `official_name` VARCHAR(255) DEFAULT NULL,
  `service_type` VARCHAR(64) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `military_services_code_uq` (`code`),
  KEY `military_services_country_idx` (`country_id`),
  KEY `military_services_active_idx` (`active`),
  CONSTRAINT `fk_military_services_country`
    FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `military_entity_types` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(64) NOT NULL,
  `label_fr` VARCHAR(120) NOT NULL,
  `label_en` VARCHAR(120) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `military_entity_types_code_uq` (`code`),
  KEY `military_entity_types_sort_idx` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `military_functions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(64) NOT NULL,
  `label_fr` VARCHAR(120) NOT NULL,
  `label_en` VARCHAR(120) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `category` VARCHAR(64) DEFAULT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `military_functions_code_uq` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `military_specialties` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(64) NOT NULL,
  `label_fr` VARCHAR(120) NOT NULL,
  `label_en` VARCHAR(120) NOT NULL,
  `category` VARCHAR(64) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `military_specialties_code_uq` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `military_domains` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(64) NOT NULL,
  `label_fr` VARCHAR(120) NOT NULL,
  `label_en` VARCHAR(120) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `military_domains_code_uq` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `military_classifications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(64) NOT NULL,
  `label_fr` VARCHAR(120) NOT NULL,
  `label_en` VARCHAR(120) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `military_classifications_code_uq` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `military_sources` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `publisher` VARCHAR(255) DEFAULT NULL,
  `url` VARCHAR(512) DEFAULT NULL,
  `source_type` VARCHAR(64) DEFAULT NULL,
  `published_at` DATE DEFAULT NULL,
  `checked_at` DATE DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `military_sources_type_idx` (`source_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `military_units` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` BIGINT UNSIGNED DEFAULT NULL,
  `country_id` BIGINT UNSIGNED NOT NULL,
  `service_id` BIGINT UNSIGNED DEFAULT NULL,
  `entity_type_id` BIGINT UNSIGNED NOT NULL,
  `code` VARCHAR(64) NOT NULL,
  `slug` VARCHAR(120) NOT NULL,
  `official_name` VARCHAR(255) NOT NULL,
  `short_name` VARCHAR(120) DEFAULT NULL,
  `display_name` VARCHAR(255) NOT NULL,
  `international_name` VARCHAR(255) DEFAULT NULL,
  `description_short` TEXT DEFAULT NULL,
  `description_long` TEXT DEFAULT NULL,
  `mission_summary` TEXT DEFAULT NULL,
  `functions_summary` TEXT DEFAULT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'active',
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `hierarchy_level` INT NOT NULL DEFAULT 0,
  `sort_order` INT NOT NULL DEFAULT 0,
  `founded_at` DATE DEFAULT NULL,
  `dissolved_at` DATE DEFAULT NULL,
  `official_website` VARCHAR(512) DEFAULT NULL,
  `verified_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `military_units_code_uq` (`code`),
  UNIQUE KEY `military_units_slug_uq` (`slug`),
  KEY `military_units_parent_idx` (`parent_id`),
  KEY `military_units_country_idx` (`country_id`),
  KEY `military_units_service_idx` (`service_id`),
  KEY `military_units_entity_type_idx` (`entity_type_id`),
  KEY `military_units_short_name_idx` (`short_name`),
  KEY `military_units_active_idx` (`active`),
  KEY `military_units_status_idx` (`status`),
  KEY `military_units_sort_idx` (`sort_order`),
  CONSTRAINT `fk_military_units_parent`
    FOREIGN KEY (`parent_id`) REFERENCES `military_units` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_military_units_country`
    FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_military_units_service`
    FOREIGN KEY (`service_id`) REFERENCES `military_services` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_military_units_entity_type`
    FOREIGN KEY (`entity_type_id`) REFERENCES `military_entity_types` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `military_unit_aliases` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `unit_id` BIGINT UNSIGNED NOT NULL,
  `alias` VARCHAR(255) NOT NULL,
  `alias_type` VARCHAR(64) NOT NULL DEFAULT 'COMMON_NAME',
  `language` VARCHAR(8) DEFAULT NULL,
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
  `searchable` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `military_unit_aliases_unit_alias_uq` (`unit_id`, `alias`),
  KEY `military_unit_aliases_alias_idx` (`alias`),
  KEY `military_unit_aliases_searchable_idx` (`searchable`),
  KEY `military_unit_aliases_type_idx` (`alias_type`),
  CONSTRAINT `fk_military_unit_aliases_unit`
    FOREIGN KEY (`unit_id`) REFERENCES `military_units` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `military_unit_functions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `unit_id` BIGINT UNSIGNED NOT NULL,
  `function_id` BIGINT UNSIGNED NOT NULL,
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
  `description` TEXT DEFAULT NULL,
  `source_id` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `military_unit_functions_uq` (`unit_id`, `function_id`),
  KEY `military_unit_functions_function_idx` (`function_id`),
  CONSTRAINT `fk_military_unit_functions_unit`
    FOREIGN KEY (`unit_id`) REFERENCES `military_units` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_military_unit_functions_function`
    FOREIGN KEY (`function_id`) REFERENCES `military_functions` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_military_unit_functions_source`
    FOREIGN KEY (`source_id`) REFERENCES `military_sources` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `military_unit_specialties` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `unit_id` BIGINT UNSIGNED NOT NULL,
  `specialty_id` BIGINT UNSIGNED NOT NULL,
  `importance` TINYINT UNSIGNED DEFAULT NULL,
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
  `description` TEXT DEFAULT NULL,
  `source_id` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `military_unit_specialties_uq` (`unit_id`, `specialty_id`),
  KEY `military_unit_specialties_specialty_idx` (`specialty_id`),
  CONSTRAINT `fk_military_unit_specialties_unit`
    FOREIGN KEY (`unit_id`) REFERENCES `military_units` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_military_unit_specialties_specialty`
    FOREIGN KEY (`specialty_id`) REFERENCES `military_specialties` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_military_unit_specialties_source`
    FOREIGN KEY (`source_id`) REFERENCES `military_sources` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `military_unit_domains` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `unit_id` BIGINT UNSIGNED NOT NULL,
  `domain_id` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `military_unit_domains_uq` (`unit_id`, `domain_id`),
  KEY `military_unit_domains_domain_idx` (`domain_id`),
  CONSTRAINT `fk_military_unit_domains_unit`
    FOREIGN KEY (`unit_id`) REFERENCES `military_units` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_military_unit_domains_domain`
    FOREIGN KEY (`domain_id`) REFERENCES `military_domains` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `military_unit_classifications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `unit_id` BIGINT UNSIGNED NOT NULL,
  `classification_id` BIGINT UNSIGNED NOT NULL,
  `is_primary` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `military_unit_classifications_uq` (`unit_id`, `classification_id`),
  KEY `military_unit_classifications_class_idx` (`classification_id`),
  CONSTRAINT `fk_military_unit_classifications_unit`
    FOREIGN KEY (`unit_id`) REFERENCES `military_units` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_military_unit_classifications_class`
    FOREIGN KEY (`classification_id`) REFERENCES `military_classifications` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `military_unit_sources` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `unit_id` BIGINT UNSIGNED NOT NULL,
  `source_id` BIGINT UNSIGNED NOT NULL,
  `information_type` VARCHAR(64) NOT NULL DEFAULT 'IDENTITY',
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `military_unit_sources_uq` (`unit_id`, `source_id`, `information_type`),
  KEY `military_unit_sources_source_idx` (`source_id`),
  CONSTRAINT `fk_military_unit_sources_unit`
    FOREIGN KEY (`unit_id`) REFERENCES `military_units` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_military_unit_sources_source`
    FOREIGN KEY (`source_id`) REFERENCES `military_sources` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `military_unit_history` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `unit_id` BIGINT UNSIGNED NOT NULL,
  `event_type` VARCHAR(64) NOT NULL,
  `event_date` DATE DEFAULT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `source_id` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `military_unit_history_unit_idx` (`unit_id`),
  KEY `military_unit_history_type_idx` (`event_type`),
  CONSTRAINT `fk_military_unit_history_unit`
    FOREIGN KEY (`unit_id`) REFERENCES `military_units` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_military_unit_history_source`
    FOREIGN KEY (`source_id`) REFERENCES `military_sources` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `military_unit_relationships` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_unit_id` BIGINT UNSIGNED NOT NULL,
  `child_unit_id` BIGINT UNSIGNED NOT NULL,
  `relationship_type` VARCHAR(64) NOT NULL,
  `valid_from` DATE DEFAULT NULL,
  `valid_until` DATE DEFAULT NULL,
  `is_current` TINYINT(1) NOT NULL DEFAULT 1,
  `source_id` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `military_unit_relationships_uq` (`parent_unit_id`, `child_unit_id`, `relationship_type`),
  KEY `military_unit_relationships_child_idx` (`child_unit_id`),
  KEY `military_unit_relationships_type_idx` (`relationship_type`),
  CONSTRAINT `fk_military_unit_relationships_parent`
    FOREIGN KEY (`parent_unit_id`) REFERENCES `military_units` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_military_unit_relationships_child`
    FOREIGN KEY (`child_unit_id`) REFERENCES `military_units` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_military_unit_relationships_source`
    FOREIGN KEY (`source_id`) REFERENCES `military_sources` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tenant_military_unit_affiliations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `military_unit_id` BIGINT UNSIGNED NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_military_unit_affiliations_uq` (`tenant_id`, `military_unit_id`),
  KEY `tenant_military_unit_affiliations_unit_idx` (`military_unit_id`),
  CONSTRAINT `fk_tenant_mil_aff_tenant`
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tenant_mil_aff_unit`
    FOREIGN KEY (`military_unit_id`) REFERENCES `military_units` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
