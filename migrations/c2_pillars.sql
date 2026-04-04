-- COMSPEC Overwatch — 6 piliers C2
-- Tables partagées par mission_id (VARCHAR, ex. op_tanoa_07 ou tenant_1_map_1)
-- Exécution : après schema.sql (référence tenants)

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ========== Pilier 1 — Fire Support ==========
CREATE TABLE IF NOT EXISTS `fire_units` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `mission_id` varchar(128) NOT NULL,
  `callsign` varchar(128) NOT NULL,
  `vehicle_class` varchar(255) DEFAULT NULL,
  `weapon_system` varchar(128) DEFAULT NULL,
  `pos_x` decimal(15,4) NOT NULL DEFAULT 0,
  `pos_y` decimal(15,4) NOT NULL DEFAULT 0,
  `pos_z` decimal(15,4) DEFAULT 0,
  `heading` decimal(10,4) DEFAULT NULL,
  `side` varchar(32) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'active',
  `last_update_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `mission_id` (`mission_id`),
  KEY `mission_callsign` (`mission_id`,`callsign`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `fire_tables` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `weapon_system` varchar(128) NOT NULL,
  `ammo_type` varchar(64) NOT NULL,
  `min_range` int unsigned DEFAULT 0,
  `max_range` int unsigned DEFAULT 0,
  `table_json` json NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `weapon_ammo` (`weapon_system`,`ammo_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========== Pilier 2 — Danger Zones ==========
CREATE TABLE IF NOT EXISTS `danger_zones` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `mission_id` varchar(128) NOT NULL,
  `zone_type` varchar(64) NOT NULL,
  `label` varchar(255) DEFAULT NULL,
  `color` varchar(32) DEFAULT '#ff0000',
  `fill_opacity` decimal(3,2) DEFAULT 0.25,
  `stroke_width` int unsigned DEFAULT 2,
  `geometry_type` varchar(32) NOT NULL,
  `geometry_json` json NOT NULL,
  `side_visibility_json` json DEFAULT NULL,
  `threat_level` varchar(32) DEFAULT 'MEDIUM',
  `active` tinyint(1) DEFAULT 1,
  `created_by` varchar(128) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `mission_id` (`mission_id`),
  KEY `mission_active` (`mission_id`,`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========== Pilier 3 — Logistics ==========
CREATE TABLE IF NOT EXISTS `asset_logistics_status` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `mission_id` varchar(128) NOT NULL,
  `asset_id` varchar(128) NOT NULL,
  `callsign` varchar(128) NOT NULL,
  `vehicle_class` varchar(255) DEFAULT NULL,
  `fuel_ratio` decimal(5,4) DEFAULT NULL,
  `ammo_state_json` json DEFAULT NULL,
  `damage_ratio` decimal(5,4) DEFAULT NULL,
  `crew_count` int unsigned DEFAULT NULL,
  `cargo_slots_free` int unsigned DEFAULT NULL,
  `slingload_capable` tinyint(1) DEFAULT 0,
  `last_update_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mission_asset` (`mission_id`,`asset_id`),
  KEY `mission_id` (`mission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `asset_logistics_status_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `mission_id` varchar(128) NOT NULL,
  `asset_id` varchar(128) NOT NULL,
  `callsign` varchar(128) NOT NULL,
  `fuel_ratio` decimal(5,4) DEFAULT NULL,
  `damage_ratio` decimal(5,4) DEFAULT NULL,
  `snapshot_json` json DEFAULT NULL,
  `logged_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `mission_logged` (`mission_id`,`logged_at`),
  KEY `mission_asset_logged` (`mission_id`,`asset_id`,`logged_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========== Pilier 4 — SITREP (Intel fusion) ==========
CREATE TABLE IF NOT EXISTS `intel_reports` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `mission_id` varchar(128) NOT NULL,
  `source_callsign` varchar(128) DEFAULT NULL,
  `report_type` varchar(64) DEFAULT NULL,
  `target_type` varchar(64) DEFAULT NULL,
  `pos_x` decimal(15,4) NOT NULL,
  `pos_y` decimal(15,4) NOT NULL,
  `pos_z` decimal(15,4) DEFAULT NULL,
  `confidence_score` int unsigned DEFAULT 0,
  `raw_payload_json` json DEFAULT NULL,
  `first_seen_at` datetime NOT NULL,
  `last_seen_at` datetime NOT NULL,
  `merged_count` int unsigned DEFAULT 1,
  `status` varchar(32) DEFAULT 'TEMPORARY',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `mission_id` (`mission_id`),
  KEY `mission_status` (`mission_id`,`status`),
  KEY `mission_last_seen` (`mission_id`,`last_seen_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `intel_reports_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `intel_report_id` int unsigned NOT NULL,
  `source_callsign` varchar(128) DEFAULT NULL,
  `payload_json` json DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `intel_report_id` (`intel_report_id`),
  CONSTRAINT `intel_reports_events_report_fk` FOREIGN KEY (`intel_report_id`) REFERENCES `intel_reports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========== Pilier 5 — Replay (AAR) ==========
CREATE TABLE IF NOT EXISTS `logs_positions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `mission_id` varchar(128) NOT NULL,
  `unit_id` varchar(128) NOT NULL,
  `callsign` varchar(128) NOT NULL,
  `unit_type` varchar(64) DEFAULT NULL,
  `side` varchar(32) DEFAULT NULL,
  `pos_x` decimal(15,4) NOT NULL,
  `pos_y` decimal(15,4) NOT NULL,
  `pos_z` decimal(15,4) DEFAULT NULL,
  `heading` decimal(10,4) DEFAULT NULL,
  `speed` decimal(10,4) DEFAULT NULL,
  `state_json` json DEFAULT NULL,
  `logged_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `mission_logged` (`mission_id`,`logged_at`),
  KEY `mission_unit_logged` (`mission_id`,`unit_id`,`logged_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========== Pilier 6 — IFF ==========
CREATE TABLE IF NOT EXISTS `iff_challenges` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `mission_id` varchar(128) NOT NULL,
  `code` varchar(64) NOT NULL,
  `valid_from` datetime NOT NULL,
  `valid_until` datetime NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `mission_id` (`mission_id`),
  KEY `mission_valid` (`mission_id`,`valid_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `iff_asset_status` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `mission_id` varchar(128) NOT NULL,
  `asset_id` varchar(128) NOT NULL,
  `callsign` varchar(128) NOT NULL,
  `platform_type` varchar(64) DEFAULT NULL,
  `current_challenge_id` int unsigned DEFAULT NULL,
  `response_code` varchar(64) DEFAULT NULL,
  `response_status` varchar(32) DEFAULT 'PENDING',
  `responded_at` datetime DEFAULT NULL,
  `grace_until` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mission_asset` (`mission_id`,`asset_id`),
  KEY `mission_id` (`mission_id`),
  KEY `current_challenge_id` (`current_challenge_id`),
  CONSTRAINT `iff_asset_status_challenge_fk` FOREIGN KEY (`current_challenge_id`) REFERENCES `iff_challenges` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- Données de test : une table de tir MK6 / HE (optionnel)
INSERT IGNORE INTO `fire_tables` (`weapon_system`, `ammo_type`, `min_range`, `max_range`, `table_json`) VALUES
('MK6', 'HE', 0, 1200, '[{"range":200,"elevation_mils":1520,"charge":0,"tof":8.4},{"range":300,"elevation_mils":1488,"charge":0,"tof":10.1},{"range":400,"elevation_mils":1450,"charge":1,"tof":11.9},{"range":500,"elevation_mils":1410,"charge":1,"tof":13.8},{"range":600,"elevation_mils":1365,"charge":2,"tof":15.9},{"range":800,"elevation_mils":1270,"charge":2,"tof":19.8},{"range":1000,"elevation_mils":1170,"charge":3,"tof":24.1}]');
