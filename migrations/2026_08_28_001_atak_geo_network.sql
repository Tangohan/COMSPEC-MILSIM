-- Réseau géographique ATAK : lieux nommés et segments routiers (ingest mod → planification web).
-- Portable : pas de FK ni COMMENT — exécutable par AtakGeoNetworkSchema et run-migrations.

CREATE TABLE IF NOT EXISTS atak_geo_places (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    map_id INT UNSIGNED NOT NULL DEFAULT 1,
    source_id VARCHAR(128) NOT NULL,
    place_type ENUM('CITY', 'TOWN', 'VILLAGE', 'LANDMARK', 'INTERSECTION', 'OTHER') NOT NULL DEFAULT 'OTHER',
    name VARCHAR(200) NOT NULL DEFAULT '',
    pos_x DECIMAL(12, 4) NOT NULL,
    pos_y DECIMAL(12, 4) NOT NULL,
    pos_z DECIMAL(12, 4) NULL,
    radius_m SMALLINT UNSIGNED NULL,
    meta_json TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    UNIQUE KEY uk_geo_place (tenant_id, map_id, source_id),
    INDEX idx_geo_place_bbox (tenant_id, map_id, pos_x, pos_y),
    INDEX idx_geo_place_type (tenant_id, map_id, place_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS atak_geo_road_segments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    map_id INT UNSIGNED NOT NULL DEFAULT 1,
    source_id VARCHAR(128) NOT NULL,
    node_a_x DECIMAL(12, 4) NOT NULL,
    node_a_y DECIMAL(12, 4) NOT NULL,
    node_b_x DECIMAL(12, 4) NOT NULL,
    node_b_y DECIMAL(12, 4) NOT NULL,
    length_m DECIMAL(10, 2) NOT NULL DEFAULT 0,
    road_class ENUM('HIGHWAY', 'PRIMARY', 'SECONDARY', 'TRACK', 'OTHER') NOT NULL DEFAULT 'OTHER',
    one_way TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    UNIQUE KEY uk_geo_road (tenant_id, map_id, source_id),
    INDEX idx_geo_road_bbox (tenant_id, map_id, node_a_x, node_a_y),
    INDEX idx_geo_road_bbox_b (tenant_id, map_id, node_b_x, node_b_y)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
