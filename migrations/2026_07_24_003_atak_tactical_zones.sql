-- Migration : Zones tactiques enrichies (LZ, DZ, Objectives, Danger Zones)

-- Table des zones tactiques
CREATE TABLE IF NOT EXISTS atak_tactical_zones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    context_id INT UNSIGNED NOT NULL COMMENT 'Contexte opérationnel',
    
    -- Identification
    zone_name VARCHAR(200) NOT NULL,
    zone_code VARCHAR(50) NULL COMMENT 'Code court (ex: LZ-ALPHA, DZ-BRAVO)',
    
    -- Type de zone
    zone_type ENUM(
        'LZ', 'DZ', 'OBJECTIVE', 'DANGER_ZONE', 'NO_GO_AREA',
        'EXTRACT_POINT', 'RALLY_POINT', 'SECTOR', 'AO', 'ROZ',
        'RESTRICTED_AREA', 'FREE_FIRE_ZONE', 'SAFE_ZONE', 'OTHER'
    ) NOT NULL,
    
    -- Sous-type optionnel
    subtype VARCHAR(100) NULL COMMENT 'Sous-catégorie spécifique',
    
    -- Description
    description TEXT NULL,
    purpose TEXT NULL COMMENT 'Objectif de la zone',
    
    -- Géométrie (plusieurs formats supportés)
    geometry_type ENUM('CIRCLE', 'ELLIPSE', 'RECTANGLE', 'POLYGON', 'POINT') NOT NULL,
    
    -- Centre de la zone
    center_x DECIMAL(12, 4) NOT NULL,
    center_y DECIMAL(12, 4) NOT NULL,
    center_z DECIMAL(12, 4) NULL COMMENT 'Altitude si pertinent',
    
    -- Paramètres géométriques (selon geometry_type)
    radius DECIMAL(10, 2) NULL COMMENT 'Rayon pour cercle (en mètres)',
    radius_major DECIMAL(10, 2) NULL COMMENT 'Rayon majeur pour ellipse',
    radius_minor DECIMAL(10, 2) NULL COMMENT 'Rayon mineur pour ellipse',
    rotation DECIMAL(6, 2) NULL COMMENT 'Rotation en degrés',
    width DECIMAL(10, 2) NULL COMMENT 'Largeur pour rectangle',
    height DECIMAL(10, 2) NULL COMMENT 'Hauteur pour rectangle',
    
    -- Polygone (points)
    polygon_points JSON NULL COMMENT 'Liste de points [x,y] pour polygone',
    
    -- Propriétés opérationnelles
    status ENUM('PLANNED', 'ACTIVE', 'INACTIVE', 'COMPLETED', 'CANCELLED') DEFAULT 'PLANNED',
    priority ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL') DEFAULT 'MEDIUM',
    threat_level ENUM('NONE', 'LOW', 'MEDIUM', 'HIGH', 'CRITICAL') DEFAULT 'NONE',
    
    -- Temporalité
    active_from DATETIME NULL COMMENT 'Date/heure d\'activation',
    active_until DATETIME NULL COMMENT 'Date/heure de désactivation',
    is_temporary BOOLEAN DEFAULT FALSE,
    
    -- Alertes et règles
    alert_on_entry BOOLEAN DEFAULT FALSE COMMENT 'Alerte quand unité entre dans la zone',
    alert_on_exit BOOLEAN DEFAULT FALSE COMMENT 'Alerte quand unité sort',
    alert_sound VARCHAR(100) NULL COMMENT 'Son d\'alerte à jouer',
    alert_message TEXT NULL COMMENT 'Message d\'alerte à afficher',
    
    -- Règles d\'engagement
    roe JSON NULL COMMENT 'Rules of Engagement spécifiques à la zone',
    restrictions TEXT NULL COMMENT 'Restrictions applicables',
    
    -- Affichage
    fill_color VARCHAR(20) NULL COMMENT 'Couleur de remplissage (hex ou nom)',
    border_color VARCHAR(20) NULL COMMENT 'Couleur de bordure',
    opacity DECIMAL(3, 2) DEFAULT 0.30 COMMENT 'Opacité (0.0 à 1.0)',
    border_width INT UNSIGNED DEFAULT 2 COMMENT 'Épaisseur de bordure en pixels',
    is_visible BOOLEAN DEFAULT TRUE,
    
    -- Icône centrale (optionnelle)
    icon_type VARCHAR(100) NULL,
    show_label BOOLEAN DEFAULT TRUE,
    label_size ENUM('SMALL', 'MEDIUM', 'LARGE') DEFAULT 'MEDIUM',
    
    -- Visibilité et permissions
    visibility_level ENUM('ALL', 'COMMAND_ONLY', 'RESTRICTED') DEFAULT 'ALL',
    restricted_to_units JSON NULL COMMENT 'Liste des unités autorisées à voir',
    
    -- Propriétés additionnelles
    properties JSON NULL COMMENT 'Propriétés spécifiques par type de zone',
    
    -- Relations
    parent_zone_id INT UNSIGNED NULL COMMENT 'Zone parente si hiérarchie',
    related_poi_id INT UNSIGNED NULL COMMENT 'POI lié',
    
    -- Métadonnées
    created_by_user_id INT UNSIGNED NULL,
    updated_by_user_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    
    -- Index
    INDEX idx_tenant_context (tenant_id, context_id),
    INDEX idx_zone_type (zone_type),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_threat (threat_level),
    INDEX idx_center_position (center_x, center_y),
    INDEX idx_active_period (active_from, active_until),
    INDEX idx_parent (parent_zone_id),
    
    -- Contraintes
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (parent_zone_id) REFERENCES atak_tactical_zones(id) ON DELETE SET NULL,
    FOREIGN KEY (related_poi_id) REFERENCES atak_poi(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Zones tactiques : LZ, DZ, objectifs, danger zones, etc.';

-- Table des alertes de zone (log des entrées/sorties)
CREATE TABLE IF NOT EXISTS atak_zone_alerts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    zone_id INT UNSIGNED NOT NULL,
    
    -- Événement
    alert_type ENUM('ENTRY', 'EXIT', 'PROXIMITY') NOT NULL,
    alerted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    -- Unité concernée
    unit_user_id INT UNSIGNED NULL,
    unit_callsign VARCHAR(100) NULL,
    unit_steam_id VARCHAR(50) NULL,
    
    -- Position au moment de l\'alerte
    unit_pos_x DECIMAL(12, 4) NULL,
    unit_pos_y DECIMAL(12, 4) NULL,
    
    -- Statut
    acknowledged BOOLEAN DEFAULT FALSE,
    acknowledged_by_user_id INT UNSIGNED NULL,
    acknowledged_at DATETIME NULL,
    
    -- Métadonnées
    properties JSON NULL COMMENT 'Données additionnelles',
    
    -- Index
    INDEX idx_zone (zone_id),
    INDEX idx_unit (unit_user_id, unit_steam_id),
    INDEX idx_alerted_at (alerted_at),
    INDEX idx_alert_type (alert_type),
    
    -- Contraintes
    FOREIGN KEY (zone_id) REFERENCES atak_tactical_zones(id) ON DELETE CASCADE,
    FOREIGN KEY (unit_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (acknowledged_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Log des alertes d\'entrée/sortie de zones tactiques';

-- Vue enrichie des zones actives
CREATE OR REPLACE VIEW v_atak_active_zones AS
SELECT 
    z.*,
    creator_u.username AS created_by_username,
    (SELECT COUNT(*) FROM atak_zone_alerts WHERE zone_id = z.id) AS alert_count,
    (SELECT COUNT(*) FROM atak_zone_alerts WHERE zone_id = z.id AND acknowledged = FALSE) AS unacknowledged_alert_count,
    CASE 
        WHEN z.active_from IS NULL AND z.active_until IS NULL THEN TRUE
        WHEN z.active_from IS NULL AND NOW() <= z.active_until THEN TRUE
        WHEN z.active_until IS NULL AND NOW() >= z.active_from THEN TRUE
        WHEN NOW() BETWEEN z.active_from AND z.active_until THEN TRUE
        ELSE FALSE
    END AS is_currently_active
FROM atak_tactical_zones z
LEFT JOIN users creator_u ON z.created_by_user_id = creator_u.id
WHERE z.deleted_at IS NULL
  AND z.status IN ('PLANNED', 'ACTIVE');
