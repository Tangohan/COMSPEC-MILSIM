-- Migration : Système QRF (Quick Reaction Force) et demandes d'appui

-- Table des demandes QRF
CREATE TABLE IF NOT EXISTS atak_qrf_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    context_id INT UNSIGNED NOT NULL,
    
    -- Identification
    qrf_number VARCHAR(50) NOT NULL COMMENT 'Numéro QRF (ex: QRF-001)',
    priority ENUM('ROUTINE', 'PRIORITY', 'IMMEDIATE', 'FLASH') NOT NULL DEFAULT 'IMMEDIATE',
    
    -- Localisation contact
    contact_pos_x DECIMAL(12, 4) NOT NULL,
    contact_pos_y DECIMAL(12, 4) NOT NULL,
    grid_reference VARCHAR(50) NULL,
    location_description TEXT NULL,
    
    -- Situation tactique
    threat_type ENUM('INFANTRY', 'ARMORED', 'AIRCRAFT', 'AMBUSH', 'OVERRUN', 'SURROUNDED', 'OTHER') NOT NULL,
    threat_description TEXT NULL COMMENT 'Description détaillée de la menace',
    enemy_strength ENUM('UNKNOWN', 'FIRE_TEAM', 'SQUAD', 'PLATOON', 'COMPANY', 'OVERWHELMING') DEFAULT 'UNKNOWN',
    enemy_disposition TEXT NULL COMMENT 'Disposition ennemie',
    
    -- Unité en difficulté
    requesting_unit VARCHAR(200) NOT NULL,
    requesting_callsign VARCHAR(100) NULL,
    requesting_user_id INT UNSIGNED NULL,
    requesting_steam_id VARCHAR(50) NULL,
    
    -- Effectifs amis sur place
    friendly_strength INT UNSIGNED NULL COMMENT 'Nombre de combattants amis',
    friendly_casualties INT UNSIGNED DEFAULT 0,
    friendly_status ENUM('HOLDING', 'PINNED', 'FALLING_BACK', 'SURROUNDED', 'OVERRUN') DEFAULT 'PINNED',
    
    -- Appui demandé
    support_requested JSON NULL COMMENT 'Types d\'appui demandés : infantry, armor, cas, medevac, etc.',
    
    -- Informations tactiques
    enemy_weapons JSON NULL COMMENT 'Armement ennemi observé',
    terrain_description TEXT NULL,
    best_approach TEXT NULL COMMENT 'Meilleure route d\'approche pour QRF',
    hazards TEXT NULL COMMENT 'Dangers à signaler (mines, IED, etc.)',
    
    -- Temporalité
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    urgency_expires_at DATETIME NULL COMMENT 'Deadline avant effondrement situation',
    
    -- Statut et workflow
    status ENUM('REQUESTED', 'ACKNOWLEDGED', 'QRF_ASSIGNED', 'QRF_ENROUTE', 'QRF_ENGAGED', 'SITUATION_STABILIZED', 'COMPLETED', 'CANCELLED') DEFAULT 'REQUESTED',
    
    -- QRF assignée
    assigned_qrf_unit VARCHAR(200) NULL,
    assigned_qrf_callsign VARCHAR(100) NULL,
    assigned_qrf_leader_user_id INT UNSIGNED NULL,
    assigned_at DATETIME NULL,
    
    -- Position QRF (mise à jour pendant déplacement)
    qrf_current_pos_x DECIMAL(12, 4) NULL,
    qrf_current_pos_y DECIMAL(12, 4) NULL,
    qrf_eta DATETIME NULL COMMENT 'ETA sur zone contact',
    
    -- Timeline
    acknowledged_at DATETIME NULL,
    qrf_departed_at DATETIME NULL,
    qrf_arrived_at DATETIME NULL,
    situation_stabilized_at DATETIME NULL,
    completed_at DATETIME NULL,
    cancelled_at DATETIME NULL,
    cancellation_reason TEXT NULL,
    
    -- Résultat
    outcome ENUM('SUCCESS', 'PARTIAL_SUCCESS', 'FAILURE', 'CANCELLED', 'ONGOING') NULL,
    outcome_description TEXT NULL,
    enemy_kia INT UNSIGNED NULL,
    friendly_additional_casualties INT UNSIGNED NULL,
    
    -- Métadonnées
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Index
    INDEX idx_tenant_context (tenant_id, context_id),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_requested_at (requested_at),
    INDEX idx_assigned_qrf (assigned_qrf_callsign),
    INDEX idx_requesting_unit (requesting_callsign),
    
    -- Contraintes
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (requesting_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_qrf_leader_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Demandes QRF (Quick Reaction Force) et appui immédiat';

-- Table des mises à jour situation QRF
CREATE TABLE IF NOT EXISTS atak_qrf_sitrep_updates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    qrf_request_id INT UNSIGNED NOT NULL,
    
    -- Mise à jour
    update_type ENUM('STATUS_CHANGE', 'POSITION_UPDATE', 'SITUATION_UPDATE', 'CONTACT_REPORT') NOT NULL,
    update_message TEXT NOT NULL,
    
    -- Position au moment de la mise à jour
    pos_x DECIMAL(12, 4) NULL,
    pos_y DECIMAL(12, 4) NULL,
    
    -- Auteur
    updated_by_callsign VARCHAR(100) NULL,
    updated_by_user_id INT UNSIGNED NULL,
    is_from_qrf BOOLEAN DEFAULT FALSE COMMENT 'TRUE si update de la QRF, FALSE si de l\'unité en difficulté',
    
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    -- Index
    INDEX idx_qrf_request (qrf_request_id),
    INDEX idx_updated_at (updated_at),
    INDEX idx_update_type (update_type),
    
    -- Contraintes
    FOREIGN KEY (qrf_request_id) REFERENCES atak_qrf_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Mises à jour situation temps réel pour QRF';

-- Table des waypoints QRF (route vers zone contact)
CREATE TABLE IF NOT EXISTS atak_qrf_waypoints (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    qrf_request_id INT UNSIGNED NOT NULL,
    
    sequence_number INT UNSIGNED NOT NULL COMMENT 'Ordre du waypoint',
    
    pos_x DECIMAL(12, 4) NOT NULL,
    pos_y DECIMAL(12, 4) NOT NULL,
    
    waypoint_type ENUM('RALLY_POINT', 'CHECKPOINT', 'OVERWATCH', 'ASSAULT_POSITION', 'OBJECTIVE') DEFAULT 'CHECKPOINT',
    waypoint_name VARCHAR(200) NULL,
    description TEXT NULL,
    
    reached BOOLEAN DEFAULT FALSE,
    reached_at DATETIME NULL,
    
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    -- Index
    INDEX idx_qrf_request (qrf_request_id),
    INDEX idx_sequence (qrf_request_id, sequence_number),
    
    -- Contraintes
    FOREIGN KEY (qrf_request_id) REFERENCES atak_qrf_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Waypoints de route pour QRF vers zone de contact';

-- Vue enrichie des QRF actives
CREATE OR REPLACE VIEW v_atak_active_qrf AS
SELECT 
    q.*,
    requester_u.username AS requesting_username,
    qrf_leader_u.username AS qrf_leader_username,
    CASE 
        WHEN q.qrf_current_pos_x IS NOT NULL AND q.contact_pos_x IS NOT NULL THEN
            SQRT(POW(q.qrf_current_pos_x - q.contact_pos_x, 2) + POW(q.qrf_current_pos_y - q.contact_pos_y, 2))
        ELSE NULL
    END AS distance_to_contact,
    TIMESTAMPDIFF(MINUTE, q.requested_at, NOW()) AS minutes_since_request,
    CASE 
        WHEN q.urgency_expires_at IS NULL THEN NULL
        WHEN NOW() >= q.urgency_expires_at THEN 'EXPIRED'
        WHEN TIMESTAMPDIFF(MINUTE, NOW(), q.urgency_expires_at) <= 5 THEN 'CRITICAL'
        WHEN TIMESTAMPDIFF(MINUTE, NOW(), q.urgency_expires_at) <= 15 THEN 'WARNING'
        ELSE 'OK'
    END AS urgency_status,
    (SELECT COUNT(*) FROM atak_qrf_sitrep_updates WHERE qrf_request_id = q.id) AS sitrep_update_count,
    (SELECT COUNT(*) FROM atak_qrf_waypoints WHERE qrf_request_id = q.id AND reached = TRUE) AS waypoints_reached,
    (SELECT COUNT(*) FROM atak_qrf_waypoints WHERE qrf_request_id = q.id) AS waypoints_total
FROM atak_qrf_requests q
LEFT JOIN users requester_u ON q.requesting_user_id = requester_u.id
LEFT JOIN users qrf_leader_u ON q.assigned_qrf_leader_user_id = qrf_leader_u.id
WHERE q.status IN ('REQUESTED', 'ACKNOWLEDGED', 'QRF_ASSIGNED', 'QRF_ENROUTE', 'QRF_ENGAGED');

-- Trigger pour créer deadline urgence automatique si FLASH
DELIMITER $$

CREATE TRIGGER trg_qrf_urgency_deadline
BEFORE INSERT ON atak_qrf_requests
FOR EACH ROW
BEGIN
    IF NEW.priority = 'FLASH' AND NEW.urgency_expires_at IS NULL THEN
        SET NEW.urgency_expires_at = DATE_ADD(NEW.requested_at, INTERVAL 30 MINUTE);
    ELSEIF NEW.priority = 'IMMEDIATE' AND NEW.urgency_expires_at IS NULL THEN
        SET NEW.urgency_expires_at = DATE_ADD(NEW.requested_at, INTERVAL 60 MINUTE);
    END IF;
END$$

DELIMITER ;
