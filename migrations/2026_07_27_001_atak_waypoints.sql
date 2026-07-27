-- Migration : waypoints partagés et itinéraires de patrouille (feature 1 ATAK).
--
-- Volontairement portable : ni COMMENT, ni FOREIGN KEY, ni colonne GENERATED.
-- Le fichier est exécuté tel quel par App\Support\AtakWaypointsSchema (filet de
-- sécurité à chaud) autant que par run-migrations.php, donc il ne doit dépendre
-- d'aucun nettoyage préalable.

CREATE TABLE IF NOT EXISTS atak_waypoint_routes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    context_id INT UNSIGNED NOT NULL,

    route_name VARCHAR(200) NOT NULL,
    route_code VARCHAR(50) NULL,
    route_type ENUM('PATROL', 'INFILTRATION', 'EXFILTRATION', 'RESUPPLY', 'MEDEVAC', 'UAV', 'OTHER') NOT NULL DEFAULT 'PATROL',
    description TEXT NULL,

    assigned_unit VARCHAR(200) NULL,
    assigned_callsign VARCHAR(100) NULL,

    status ENUM('PLANNED', 'ACTIVE', 'COMPLETED', 'ABORTED') NOT NULL DEFAULT 'PLANNED',
    started_at DATETIME NULL,
    completed_at DATETIME NULL,

    marker_color VARCHAR(32) NULL,
    is_visible TINYINT(1) NOT NULL DEFAULT 1,
    visibility_level ENUM('PUBLIC', 'UNIT', 'COMMAND') NOT NULL DEFAULT 'PUBLIC',

    created_by_user_id INT UNSIGNED NULL,
    created_by_callsign VARCHAR(100) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,

    INDEX idx_wpr_tenant_context (tenant_id, context_id),
    INDEX idx_wpr_status (tenant_id, context_id, status),
    INDEX idx_wpr_callsign (tenant_id, assigned_callsign)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS atak_waypoints (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    context_id INT UNSIGNED NOT NULL,
    route_id INT UNSIGNED NULL,

    sequence_number INT UNSIGNED NOT NULL DEFAULT 1,

    label VARCHAR(200) NULL,
    waypoint_type ENUM('CHECKPOINT', 'RALLY_POINT', 'OVERWATCH', 'ASSAULT_POSITION', 'OBJECTIVE', 'LZ', 'DZ', 'OTHER') NOT NULL DEFAULT 'CHECKPOINT',
    description TEXT NULL,

    pos_x DECIMAL(12, 4) NOT NULL,
    pos_y DECIMAL(12, 4) NOT NULL,
    pos_z DECIMAL(12, 4) NULL,
    grid_reference VARCHAR(50) NULL,
    radius_m INT UNSIGNED NULL,

    reached TINYINT(1) NOT NULL DEFAULT 0,
    reached_at DATETIME NULL,
    reached_by_user_id INT UNSIGNED NULL,
    reached_by_callsign VARCHAR(100) NULL,

    created_by_user_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,

    INDEX idx_wp_tenant_context (tenant_id, context_id),
    INDEX idx_wp_route_sequence (route_id, sequence_number),
    INDEX idx_wp_reached (tenant_id, context_id, reached)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
