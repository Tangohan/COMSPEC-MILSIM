-- Cursus pilote : flotte aérienne et paliers de la communauté.
--  - pilot_aircraft_groups : familles d'appareils créées ou renommées (transport, attaque, reconnaissance… ou propres) ;
--  - pilot_aircraft : fiches d'appareils (appareil créé par la communauté, ou appareil reconnu renommé / rangé ailleurs) ;
--  - pilot_aircraft_classes : classes Arma rattachées à un appareil de la communauté (variantes) ;
--  - pilot_syllabus_steps : paliers créés par la communauté (qualification délivrée, filière, ordre, archivage) ; leurs
--    conditions sont la règle automatique de la qualification (qualification_award_rules).
-- Aucune ligne créée : sans réglage, les appareils et les cursus déjà installés comptent exactement comme avant.
-- Exécuté par database/steps/3570_pilot_fleet_and_steps.php ; idempotent (CREATE TABLE IF NOT EXISTS).

CREATE TABLE IF NOT EXISTS `pilot_aircraft_groups` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `group_key` varchar(40) NOT NULL COMMENT 'transport, attack… ou g_<nom> pour une famille de la communauté',
  `name` varchar(80) NOT NULL,
  `sort_order` int NOT NULL DEFAULT 0,
  `archived_at` datetime DEFAULT NULL COMMENT 'NULL = proposée',
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pilot_ac_group_key` (`tenant_id`, `group_key`),
  CONSTRAINT `fk_pilot_ac_group_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pilot_aircraft` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `aircraft_key` varchar(90) NOT NULL COMMENT 'uh60, class:<classe> (appareil reconnu réglé) ou ac_<nom> (appareil de la communauté)',
  `name` varchar(120) NOT NULL,
  `track` varchar(8) DEFAULT NULL COMMENT 'heli, plane, other ; NULL = déduite',
  `group_key` varchar(40) DEFAULT NULL COMMENT 'Famille ; NULL = déduite',
  `image_path` varchar(255) DEFAULT NULL COMMENT 'Image envoyée (storage/uploads)',
  `is_custom` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = créé par la communauté',
  `archived_at` datetime DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pilot_aircraft_key` (`tenant_id`, `aircraft_key`),
  CONSTRAINT `fk_pilot_aircraft_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pilot_aircraft_classes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `aircraft_id` int unsigned NOT NULL,
  `vehicle_class` varchar(80) NOT NULL COMMENT 'Classe Arma en minuscules',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pilot_ac_class` (`tenant_id`, `vehicle_class`),
  KEY `idx_pilot_ac_class_aircraft` (`aircraft_id`),
  CONSTRAINT `fk_pilot_ac_class_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pilot_ac_class_aircraft` FOREIGN KEY (`aircraft_id`) REFERENCES `pilot_aircraft` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pilot_syllabus_steps` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `definition_id` int unsigned NOT NULL COMMENT 'Qualification délivrée (personnel_qualification_definitions.id)',
  `track` varchar(8) NOT NULL DEFAULT 'heli' COMMENT 'heli, plane, other',
  `sort_order` int NOT NULL DEFAULT 0,
  `archived_at` datetime DEFAULT NULL COMMENT 'NULL = actif ; archivé = plus délivré automatiquement',
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pilot_step_definition` (`tenant_id`, `definition_id`),
  KEY `idx_pilot_step_order` (`tenant_id`, `track`, `sort_order`),
  CONSTRAINT `fk_pilot_step_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pilot_step_definition` FOREIGN KEY (`definition_id`) REFERENCES `personnel_qualification_definitions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
