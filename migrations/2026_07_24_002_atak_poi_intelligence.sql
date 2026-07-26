-- Migration : Système de Points d'Intérêt (POI) et Intelligence Tactique
-- Préférer bootstrap/atak_poi_intelligence_migration.php via run-migrations.php
-- (ce fichier .sql n’est pas exécuté automatiquement par le runner CLI).

-- Table principale des POI
CREATE TABLE IF NOT EXISTS atak_poi (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    context_id INT UNSIGNED NOT NULL COMMENT 'Contexte opérationnel',
    
    -- Identification
    poi_name VARCHAR(200) NOT NULL,
    poi_code VARCHAR(50) NULL COMMENT 'Code court (ex: OBJ-ALPHA, CACHE-01)',
    
    -- Classification
    category ENUM(
        'OBJECTIVE', 'BUILDING', 'CACHE', 'ENEMY_POSITION', 'FRIENDLY_POSITION',
        'NEUTRAL', 'HVT', 'OBSTACLE', 'SUPPLY_POINT', 'RALLY_POINT',
        'OBSERVATION_POST', 'COMMAND_POST', 'VEHICLE', 'INFRASTRUCTURE', 'OTHER'
    ) NOT NULL DEFAULT 'OTHER',
    
    affiliation ENUM('FRIENDLY', 'ENEMY', 'NEUTRAL', 'UNKNOWN') NOT NULL DEFAULT 'UNKNOWN',
    
    -- Niveau de certitude
    certainty ENUM('CONFIRMED', 'PROBABLE', 'POSSIBLE', 'TO_VERIFY') NOT NULL DEFAULT 'TO_VERIFY',
    
    -- Localisation
    pos_x DECIMAL(12, 4) NOT NULL,
    pos_y DECIMAL(12, 4) NOT NULL,
    pos_z DECIMAL(12, 4) NULL COMMENT 'Altitude si pertinent',
    grid_reference VARCHAR(50) NULL,
    
    -- Description
    description TEXT NULL COMMENT 'Description détaillée du POI',
    observed_activity TEXT NULL COMMENT 'Activités observées',
    threat_level ENUM('NONE', 'LOW', 'MEDIUM', 'HIGH', 'CRITICAL') DEFAULT 'NONE',
    
    -- Statut
    status ENUM('ACTIVE', 'NEUTRALIZED', 'DESTROYED', 'ABANDONED', 'OCCUPIED', 'UNKNOWN') DEFAULT 'ACTIVE',
    last_observed_at DATETIME NULL COMMENT 'Dernière observation confirmée',
    
    -- Source de l\'information
    source_type ENUM('VISUAL', 'REPORT', 'SIGINT', 'DRONE', 'INFORMANT', 'OTHER') NULL,
    source_reliability ENUM('RELIABLE', 'USUALLY_RELIABLE', 'FAIRLY_RELIABLE', 'UNRELIABLE', 'UNKNOWN') DEFAULT 'UNKNOWN',
    reported_by_user_id INT UNSIGNED NULL,
    reported_by_callsign VARCHAR(100) NULL,
    
    -- Propriétés additionnelles
    properties JSON NULL COMMENT 'Propriétés spécifiques (effectifs, armement, etc.)',
    
    -- Icône et affichage
    icon_type VARCHAR(100) NULL COMMENT 'Type d\'icône pour affichage carte',
    marker_color VARCHAR(20) NULL COMMENT 'Couleur du marqueur (hex ou nom)',
    
    -- Visibilité
    is_visible BOOLEAN DEFAULT TRUE,
    visibility_level ENUM('PUBLIC', 'COMMAND_ONLY', 'RESTRICTED') DEFAULT 'PUBLIC',
    
    -- Relations
    parent_poi_id INT UNSIGNED NULL COMMENT 'POI parent si hiérarchie',
    related_report_id INT UNSIGNED NULL COMMENT 'Rapport tactique lié',
    
    -- Métadonnées
    created_by_user_id INT UNSIGNED NULL,
    updated_by_user_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    
    -- Index
    INDEX idx_tenant_context (tenant_id, context_id),
    INDEX idx_category (category),
    INDEX idx_affiliation (affiliation),
    INDEX idx_status (status),
    INDEX idx_certainty (certainty),
    INDEX idx_position (pos_x, pos_y),
    INDEX idx_threat (threat_level),
    INDEX idx_parent (parent_poi_id),
    
    -- Contraintes
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (reported_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (parent_poi_id) REFERENCES atak_poi(id) ON DELETE SET NULL,
    FOREIGN KEY (related_report_id) REFERENCES atak_tactical_reports(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Points d\'intérêt et intelligence tactique géolocalisée';

-- Table des observations POI (historique)
CREATE TABLE IF NOT EXISTS atak_poi_observations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    poi_id INT UNSIGNED NOT NULL,
    
    -- Observation
    observed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    observer_user_id INT UNSIGNED NULL,
    observer_callsign VARCHAR(100) NULL,
    
    -- Détails de l\'observation
    status_at_observation ENUM('ACTIVE', 'NEUTRALIZED', 'DESTROYED', 'ABANDONED', 'OCCUPIED', 'UNKNOWN') NOT NULL,
    observed_activity TEXT NULL,
    threat_assessment ENUM('NONE', 'LOW', 'MEDIUM', 'HIGH', 'CRITICAL') NULL,
    
    notes TEXT NULL,
    
    -- Index
    INDEX idx_poi (poi_id),
    INDEX idx_observer (observer_user_id),
    INDEX idx_observed_at (observed_at),
    
    -- Contraintes
    FOREIGN KEY (poi_id) REFERENCES atak_poi(id) ON DELETE CASCADE,
    FOREIGN KEY (observer_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Historique des observations de POI';

-- Table des photos POI
CREATE TABLE IF NOT EXISTS atak_poi_photos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    poi_id INT UNSIGNED NOT NULL,
    
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(50) NULL,
    file_size INT UNSIGNED NULL,
    
    caption TEXT NULL,
    taken_at DATETIME NULL,
    taken_by_user_id INT UNSIGNED NULL,
    taken_by_callsign VARCHAR(100) NULL,
    
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    -- Index
    INDEX idx_poi (poi_id),
    INDEX idx_taken_by (taken_by_user_id),
    
    -- Contraintes
    FOREIGN KEY (poi_id) REFERENCES atak_poi(id) ON DELETE CASCADE,
    FOREIGN KEY (taken_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Photos associées aux POI';

-- Vue enrichie des POI
CREATE OR REPLACE VIEW v_atak_poi AS
SELECT 
    p.*,
    reporter_u.username AS reported_by_username,
    COALESCE(p.reported_by_callsign, CONCAT(reporter_u.first_name, ' ', reporter_u.last_name)) AS reporter_display_name,
    creator_u.username AS created_by_username,
    (SELECT COUNT(*) FROM atak_poi_photos WHERE poi_id = p.id) AS photo_count,
    (SELECT COUNT(*) FROM atak_poi_observations WHERE poi_id = p.id) AS observation_count,
    (SELECT MAX(observed_at) FROM atak_poi_observations WHERE poi_id = p.id) AS last_observation_timestamp
FROM atak_poi p
LEFT JOIN users reporter_u ON p.reported_by_user_id = reporter_u.id
LEFT JOIN users creator_u ON p.created_by_user_id = creator_u.id
WHERE p.deleted_at IS NULL;
