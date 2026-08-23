<?php

declare(strict_types=1);

/**
 * Cinématique BFT + destinations assignées (isolées par tenant).
 * Idempotent — appelée depuis run-migrations.php et AtakUnitMotionSchema::ensure().
 */
return static function (PDO $pdo): void {
    $root = dirname(__DIR__);
    require_once $root . '/bootstrap/schema_ensure_column.php';

    $tables = [
        'atak_unit_motion_samples' => <<<'SQL'
CREATE TABLE IF NOT EXISTS `atak_unit_motion_samples` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `map_id` int unsigned NOT NULL DEFAULT 1,
  `unit_kind` varchar(16) NOT NULL DEFAULT 'ground',
  `unit_id` int unsigned DEFAULT NULL,
  `unit_ref` varchar(64) NOT NULL,
  `pos_x` decimal(15,4) NOT NULL,
  `pos_y` decimal(15,4) NOT NULL,
  `pos_z` decimal(15,4) DEFAULT NULL,
  `heading_object` decimal(10,4) DEFAULT NULL,
  `speed_ms` decimal(10,4) DEFAULT NULL,
  `vel_x` decimal(10,4) DEFAULT NULL,
  `vel_y` decimal(10,4) DEFAULT NULL,
  `vel_z` decimal(10,4) DEFAULT NULL,
  `sampled_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_motion_samples_lookup` (`tenant_id`,`map_id`,`unit_kind`,`unit_ref`,`sampled_at`),
  KEY `idx_motion_samples_unit` (`tenant_id`,`unit_id`,`sampled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL,
        'atak_unit_motion' => <<<'SQL'
CREATE TABLE IF NOT EXISTS `atak_unit_motion` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `map_id` int unsigned NOT NULL DEFAULT 1,
  `unit_kind` varchar(16) NOT NULL DEFAULT 'ground',
  `unit_id` int unsigned DEFAULT NULL,
  `unit_ref` varchar(64) NOT NULL,
  `heading_object` decimal(10,4) DEFAULT NULL,
  `movement_heading` decimal(10,4) DEFAULT NULL,
  `speed_ms` decimal(10,4) DEFAULT NULL,
  `speed_avg_30` decimal(10,4) DEFAULT NULL,
  `speed_avg_60` decimal(10,4) DEFAULT NULL,
  `eta_speed_ms` decimal(10,4) DEFAULT NULL,
  `motion_status` varchar(24) NOT NULL DEFAULT 'UNKNOWN',
  `confidence` decimal(4,3) NOT NULL DEFAULT 0.000,
  `trend` varchar(24) NOT NULL DEFAULT 'UNKNOWN',
  `alt_msl` decimal(10,2) DEFAULT NULL,
  `vertical_speed` decimal(10,4) DEFAULT NULL,
  `alt_trend` varchar(16) DEFAULT NULL,
  `motion_json` json DEFAULT NULL,
  `computed_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_motion_unit` (`tenant_id`,`map_id`,`unit_kind`,`unit_ref`),
  KEY `idx_motion_map` (`tenant_id`,`map_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL,
        'atak_unit_assignments' => <<<'SQL'
CREATE TABLE IF NOT EXISTS `atak_unit_assignments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `map_id` int unsigned NOT NULL DEFAULT 1,
  `unit_kind` varchar(16) NOT NULL DEFAULT 'ground',
  `unit_id` int unsigned DEFAULT NULL,
  `unit_ref` varchar(64) NOT NULL,
  `destination_type` varchar(32) NOT NULL,
  `destination_id` varchar(64) DEFAULT NULL,
  `destination_label` varchar(160) DEFAULT NULL,
  `destination_x` decimal(15,4) DEFAULT NULL,
  `destination_y` decimal(15,4) DEFAULT NULL,
  `assignment_mode` varchar(16) NOT NULL DEFAULT 'DIRECT',
  `status` varchar(24) NOT NULL DEFAULT 'active',
  `assigned_by` int unsigned DEFAULT NULL,
  `assigned_by_label` varchar(120) DEFAULT NULL,
  `assigned_at` datetime NOT NULL,
  `arrived_at` datetime DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_assign_active` (`tenant_id`,`map_id`,`status`),
  KEY `idx_assign_unit` (`tenant_id`,`map_id`,`unit_kind`,`unit_ref`,`status`),
  KEY `idx_assign_dest` (`tenant_id`,`destination_type`,`destination_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL,
    ];

    foreach ($tables as $name => $ddl) {
        if (schema_table_exists($pdo, $name)) {
            echo "  [OK] {$name} déjà présente\n";
            continue;
        }
        try {
            $pdo->exec($ddl);
            echo "  [COMPLÉTÉ] Table créée : {$name}\n";
        } catch (Throwable $e) {
            echo "  [ATTENTION] {$name} : " . $e->getMessage() . "\n";
        }
    }
};
