-- Migration : Suivi enrichi des véhicules et assets lourds

-- Table des véhicules et assets trackés
CREATE TABLE IF NOT EXISTS atak_vehicle_tracking (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    context_id INT UNSIGNED NOT NULL,
    
    -- Identification véhicule
    vehicle_callsign VARCHAR(100) NOT NULL COMMENT 'Indicatif véhicule',
    vehicle_name VARCHAR(200) NULL COMMENT 'Nom du véhicule si nommé',
    
    -- Type et classification
    vehicle_class ENUM(
        'LIGHT_VEHICLE', 'TRUCK', 'APC', 'IFV', 'TANK', 'ARTILLERY',
        'HELICOPTER', 'FIXED_WING', 'UAV', 'BOAT', 'STATIC', 'OTHER'
    ) NOT NULL,
    vehicle_type VARCHAR(100) NULL COMMENT 'Type précis (ex: M1A2, Bradley, Apache)',
    
    -- Affiliation
    side ENUM('BLUFOR', 'OPFOR', 'INDEPENDENT', 'CIVILIAN') DEFAULT 'BLUFOR',
    unit_assigned VARCHAR(200) NULL COMMENT 'Unité à laquelle le véhicule est assigné',
    
    -- Équipage
    crew_commander_callsign VARCHAR(100) NULL,
    crew_commander_user_id INT UNSIGNED NULL,
    crew_count INT UNSIGNED DEFAULT 0,
    crew_max INT UNSIGNED NULL,
    
    -- Passagers
    passenger_count INT UNSIGNED DEFAULT 0,
    passenger_max INT UNSIGNED NULL,
    passengers_json JSON NULL COMMENT 'Liste des passagers avec callsigns',
    
    -- Position et mouvement
    pos_x DECIMAL(12, 4) NOT NULL,
    pos_y DECIMAL(12, 4) NOT NULL,
    pos_z DECIMAL(12, 4) NULL COMMENT 'Altitude',
    heading DECIMAL(6, 2) NULL COMMENT 'Cap en degrés',
    speed DECIMAL(8, 2) NULL COMMENT 'Vitesse en km/h',
    
    -- État du véhicule
    status ENUM('OPERATIONAL', 'DAMAGED', 'IMMOBILIZED', 'DESTROYED', 'ABANDONED') DEFAULT 'OPERATIONAL',
    
    -- Carburant
    fuel_percent DECIMAL(5, 2) NULL COMMENT 'Pourcentage de carburant (0-100)',
    fuel_capacity INT UNSIGNED NULL COMMENT 'Capacité réservoir en litres',
    fuel_consumption_rate DECIMAL(8, 2) NULL COMMENT 'Consommation L/100km',
    
    -- Munitions et armement
    ammo_percent DECIMAL(5, 2) NULL COMMENT 'Pourcentage munitions restantes',
    weapons JSON NULL COMMENT 'Liste des armes avec munitions par type',
    
    -- État mécanique
    engine_health DECIMAL(5, 2) NULL COMMENT 'État moteur 0-100',
    hull_health DECIMAL(5, 2) NULL COMMENT 'État coque 0-100',
    tracks_wheels_health DECIMAL(5, 2) NULL COMMENT 'État chenilles/roues 0-100',
    turret_health DECIMAL(5, 2) NULL COMMENT 'État tourelle 0-100 (si applicable)',
    
    -- Alertes
    is_fuel_critical BOOLEAN GENERATED ALWAYS AS (fuel_percent IS NOT NULL AND fuel_percent < 20) STORED,
    is_ammo_critical BOOLEAN GENERATED ALWAYS AS (ammo_percent IS NOT NULL AND ammo_percent < 20) STORED,
    is_damaged BOOLEAN GENERATED ALWAYS AS (
        status IN ('DAMAGED', 'IMMOBILIZED', 'DESTROYED') OR 
        (engine_health IS NOT NULL AND engine_health < 50) OR
        (hull_health IS NOT NULL AND hull_health < 50)
    ) STORED,
    
    -- Destination et mission
    destination_pos_x DECIMAL(12, 4) NULL,
    destination_pos_y DECIMAL(12, 4) NULL,
    mission_type ENUM('PATROL', 'TRANSPORT', 'COMBAT', 'SUPPORT', 'LOGISTICS', 'MEDEVAC', 'RECON', 'IDLE') NULL,
    mission_description TEXT NULL,
    
    -- Propriétés additionnelles
    properties JSON NULL COMMENT 'Propriétés spécifiques par type de véhicule',
    
    -- Visibilité
    is_visible BOOLEAN DEFAULT TRUE,
    
    -- Temporalité
    last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    immobilized_since DATETIME NULL,
    destroyed_at DATETIME NULL,
    
    -- Métadonnées
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Index
    INDEX idx_tenant_context (tenant_id, context_id),
    INDEX idx_vehicle_callsign (vehicle_callsign),
    INDEX idx_vehicle_class (vehicle_class),
    INDEX idx_side (side),
    INDEX idx_status (status),
    INDEX idx_position (pos_x, pos_y),
    INDEX idx_fuel_critical (is_fuel_critical),
    INDEX idx_damaged (is_damaged),
    INDEX idx_last_seen (last_seen_at),
    
    -- Contraintes
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (crew_commander_user_id) REFERENCES users(id) ON DELETE SET NULL,
    
    UNIQUE KEY uk_tenant_context_callsign (tenant_id, context_id, vehicle_callsign)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tracking enrichi des véhicules et assets lourds';

-- Table historique positions véhicules (pour replay)
CREATE TABLE IF NOT EXISTS atak_vehicle_position_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vehicle_tracking_id INT UNSIGNED NOT NULL,
    
    pos_x DECIMAL(12, 4) NOT NULL,
    pos_y DECIMAL(12, 4) NOT NULL,
    pos_z DECIMAL(12, 4) NULL,
    heading DECIMAL(6, 2) NULL,
    speed DECIMAL(8, 2) NULL,
    
    fuel_percent DECIMAL(5, 2) NULL,
    ammo_percent DECIMAL(5, 2) NULL,
    
    recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    -- Index
    INDEX idx_vehicle (vehicle_tracking_id),
    INDEX idx_recorded_at (recorded_at),
    
    -- Contraintes
    FOREIGN KEY (vehicle_tracking_id) REFERENCES atak_vehicle_tracking(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Historique positions véhicules pour replay mission';

-- Table des événements véhicule
CREATE TABLE IF NOT EXISTS atak_vehicle_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vehicle_tracking_id INT UNSIGNED NOT NULL,
    
    event_type ENUM(
        'DEPLOYED', 'MOVED', 'STOPPED', 'DAMAGED', 'REPAIRED', 'IMMOBILIZED',
        'DESTROYED', 'ABANDONED', 'RECOVERED', 'REFUELED', 'REARMED',
        'CREW_CHANGED', 'MISSION_ASSIGNED', 'MISSION_COMPLETED'
    ) NOT NULL,
    
    event_description TEXT NULL,
    
    -- Position événement
    event_pos_x DECIMAL(12, 4) NULL,
    event_pos_y DECIMAL(12, 4) NULL,
    
    -- Acteur événement
    actor_callsign VARCHAR(100) NULL,
    actor_user_id INT UNSIGNED NULL,
    
    -- Données événement
    event_data JSON NULL COMMENT 'Données additionnelles selon type événement',
    
    event_timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    -- Index
    INDEX idx_vehicle (vehicle_tracking_id),
    INDEX idx_event_type (event_type),
    INDEX idx_event_timestamp (event_timestamp),
    
    -- Contraintes
    FOREIGN KEY (vehicle_tracking_id) REFERENCES atak_vehicle_tracking(id) ON DELETE CASCADE,
    FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Événements et historique actions sur véhicules';

-- Table des demandes maintenance/ravitaillement
CREATE TABLE IF NOT EXISTS atak_vehicle_service_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vehicle_tracking_id INT UNSIGNED NOT NULL,
    
    request_type ENUM('REFUEL', 'REARM', 'REPAIR', 'MAINTENANCE', 'RECOVERY') NOT NULL,
    priority ENUM('LOW', 'MEDIUM', 'HIGH', 'URGENT') DEFAULT 'MEDIUM',
    
    request_details TEXT NULL,
    requested_by_callsign VARCHAR(100) NULL,
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    -- Position demande
    service_pos_x DECIMAL(12, 4) NULL,
    service_pos_y DECIMAL(12, 4) NULL,
    
    -- Statut
    status ENUM('REQUESTED', 'ACKNOWLEDGED', 'ENROUTE', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED') DEFAULT 'REQUESTED',
    
    -- Assignation
    assigned_to_callsign VARCHAR(100) NULL COMMENT 'Véhicule/équipe de service assigné',
    assigned_at DATETIME NULL,
    
    -- Timeline
    service_started_at DATETIME NULL,
    service_completed_at DATETIME NULL,
    
    -- Résultat
    completion_notes TEXT NULL,
    
    -- Index
    INDEX idx_vehicle (vehicle_tracking_id),
    INDEX idx_request_type (request_type),
    INDEX idx_status (status),
    INDEX idx_requested_at (requested_at),
    
    -- Contraintes
    FOREIGN KEY (vehicle_tracking_id) REFERENCES atak_vehicle_tracking(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Demandes de service pour véhicules (fuel, munitions, réparation)';

-- Vue enrichie véhicules actifs
CREATE OR REPLACE VIEW v_atak_active_vehicles AS
SELECT 
    v.*,
    commander_u.username AS crew_commander_username,
    CASE 
        WHEN v.fuel_percent IS NULL THEN 'UNKNOWN'
        WHEN v.fuel_percent < 10 THEN 'CRITICAL'
        WHEN v.fuel_percent < 20 THEN 'LOW'
        WHEN v.fuel_percent < 50 THEN 'MEDIUM'
        ELSE 'OK'
    END AS fuel_status_label,
    CASE 
        WHEN v.ammo_percent IS NULL THEN 'UNKNOWN'
        WHEN v.ammo_percent < 10 THEN 'CRITICAL'
        WHEN v.ammo_percent < 20 THEN 'LOW'
        WHEN v.ammo_percent < 50 THEN 'MEDIUM'
        ELSE 'OK'
    END AS ammo_status_label,
    CASE
        WHEN v.destination_pos_x IS NOT NULL AND v.destination_pos_y IS NOT NULL THEN
            SQRT(POW(v.destination_pos_x - v.pos_x, 2) + POW(v.destination_pos_y - v.pos_y, 2))
        ELSE NULL
    END AS distance_to_destination,
    (SELECT COUNT(*) FROM atak_vehicle_service_requests WHERE vehicle_tracking_id = v.id AND status IN ('REQUESTED', 'ACKNOWLEDGED', 'ENROUTE', 'IN_PROGRESS')) AS pending_service_requests,
    TIMESTAMPDIFF(SECOND, v.last_seen_at, NOW()) AS seconds_since_last_update
FROM atak_vehicle_tracking v
LEFT JOIN users commander_u ON v.crew_commander_user_id = commander_u.id
WHERE v.status != 'DESTROYED' AND TIMESTAMPDIFF(MINUTE, v.last_seen_at, NOW()) <= 30;

-- Trigger pour logger événement déploiement
DELIMITER $$

CREATE TRIGGER trg_vehicle_deployed
AFTER INSERT ON atak_vehicle_tracking
FOR EACH ROW
BEGIN
    INSERT INTO atak_vehicle_events (vehicle_tracking_id, event_type, event_description, event_pos_x, event_pos_y)
    VALUES (NEW.id, 'DEPLOYED', CONCAT('Véhicule ', NEW.vehicle_callsign, ' déployé'), NEW.pos_x, NEW.pos_y);
END$$

DELIMITER ;

-- Trigger pour logger événement destruction
DELIMITER $$

CREATE TRIGGER trg_vehicle_destroyed
AFTER UPDATE ON atak_vehicle_tracking
FOR EACH ROW
BEGIN
    IF OLD.status != 'DESTROYED' AND NEW.status = 'DESTROYED' THEN
        INSERT INTO atak_vehicle_events (vehicle_tracking_id, event_type, event_description, event_pos_x, event_pos_y)
        VALUES (NEW.id, 'DESTROYED', CONCAT('Véhicule ', NEW.vehicle_callsign, ' détruit'), NEW.pos_x, NEW.pos_y);
        
        UPDATE atak_vehicle_tracking SET destroyed_at = NOW() WHERE id = NEW.id;
    END IF;
END$$

DELIMITER ;
