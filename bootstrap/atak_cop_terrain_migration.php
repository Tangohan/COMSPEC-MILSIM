<?php

declare(strict_types=1);

/**
 * Relief de théâtre (grille d’altitudes statique) + événements d’analyse commandement.
 * Idempotent — appelée depuis run-migrations.php et AtakCopTerrainSchema::ensure().
 */
return static function (PDO $pdo): void {
    $root = dirname(__DIR__);
    require_once $root . '/bootstrap/schema_ensure_column.php';

    $tables = [
        'atak_terrain_grids' => <<<'SQL'
CREATE TABLE IF NOT EXISTS `atak_terrain_grids` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `map_id` int unsigned NOT NULL DEFAULT 1,
  `world_name` varchar(64) NOT NULL DEFAULT '',
  `world_size` int unsigned NOT NULL DEFAULT 0,
  `origin_x` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `origin_y` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `cell_m` smallint unsigned NOT NULL DEFAULT 50,
  `cols` int unsigned NOT NULL DEFAULT 0,
  `grid_rows` int unsigned NOT NULL DEFAULT 0,
  `heights` mediumblob DEFAULT NULL,
  `min_z` smallint DEFAULT NULL,
  `max_z` smallint DEFAULT NULL,
  `filled_cells` int unsigned NOT NULL DEFAULT 0,
  `ready` tinyint unsigned NOT NULL DEFAULT 0,
  `sampled_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_terrain_grid` (`tenant_id`,`map_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL,
        'atak_terrain_chunks' => <<<'SQL'
CREATE TABLE IF NOT EXISTS `atak_terrain_chunks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `map_id` int unsigned NOT NULL DEFAULT 1,
  `grid_id` int unsigned NOT NULL,
  `col0` int unsigned NOT NULL,
  `row0` int unsigned NOT NULL,
  `cw` smallint unsigned NOT NULL,
  `rh` smallint unsigned NOT NULL,
  `received_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_terrain_chunk` (`tenant_id`,`map_id`,`col0`,`row0`),
  KEY `idx_terrain_chunk_grid` (`grid_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL,
        'atak_unit_intel_events' => <<<'SQL'
CREATE TABLE IF NOT EXISTS `atak_unit_intel_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `map_id` int unsigned NOT NULL DEFAULT 1,
  `unit_kind` varchar(16) NOT NULL DEFAULT 'ground',
  `unit_ref` varchar(64) NOT NULL,
  `event_type` varchar(40) NOT NULL,
  `source` varchar(16) NOT NULL DEFAULT 'athena',
  `severity` varchar(16) NOT NULL DEFAULT 'info',
  `message` varchar(280) NOT NULL DEFAULT '',
  `payload_json` json DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_intel_map_time` (`tenant_id`,`map_id`,`created_at`),
  KEY `idx_intel_unit` (`tenant_id`,`map_id`,`unit_kind`,`unit_ref`,`created_at`),
  KEY `idx_intel_type` (`tenant_id`,`map_id`,`event_type`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL,
    ];

    foreach ($tables as $name => $ddl) {
        $cli = PHP_SAPI === 'cli';
        if (schema_table_exists($pdo, $name)) {
            if ($cli) {
                echo "  [OK] {$name} déjà présente\n";
            }
            continue;
        }
        try {
            $pdo->exec($ddl);
            if ($cli) {
                echo "  [COMPLÉTÉ] Table créée : {$name}\n";
            }
        } catch (Throwable $e) {
            if ($cli) {
                echo "  [ATTENTION] {$name} : " . $e->getMessage() . "\n";
            }
        }
    }

    $cli = PHP_SAPI === 'cli';

    // MariaDB : ROWS est un mot réservé. Le renommage réécrit le MEDIUMBLOB `heights` :
    // uniquement en CLI (run-migrations), jamais sur une requête HTTP ATAK.
    if ($cli
        && schema_table_exists($pdo, 'atak_terrain_grids')
        && schema_column_exists($pdo, 'atak_terrain_grids', 'rows')
        && !schema_column_exists($pdo, 'atak_terrain_grids', 'grid_rows')
    ) {
        try {
            $pdo->exec(
                'ALTER TABLE `atak_terrain_grids`
                 CHANGE `rows` `grid_rows` int unsigned NOT NULL DEFAULT 0'
            );
            echo "  [OK] atak_terrain_grids.rows → grid_rows\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] rename rows : ' . $e->getMessage() . "\n";
        }
    }
};
