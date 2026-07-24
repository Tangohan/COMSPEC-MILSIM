-- Migration : Extension système MEDEVAC avec triage et workflow complet

-- Table extension des alertes médicales avec triage TCCC
CREATE TABLE IF NOT EXISTS atak_medevac_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    context_id INT UNSIGNED NOT NULL COMMENT 'Contexte opérationnel',
    
    -- Identification
    medevac_number VARCHAR(50) NOT NULL COMMENT 'Numéro MEDEVAC (ex: MEDEVAC-001)',
    priority ENUM('URGENT', 'PRIORITY', 'ROUTINE', 'CONVENIENCE') NOT NULL DEFAULT 'URGENT',
    
    -- Ligne 1 : Localisation pickup
    pickup_grid VARCHAR(50) NOT NULL COMMENT 'Grille militaire point pickup',
    pickup_pos_x DECIMAL(12, 4) NULL,
    pickup_pos_y DECIMAL(12, 4) NULL,
    pickup_elevation INT NULL COMMENT 'Élévation en mètres',
    
    -- Ligne 2 : Fréquence radio
    radio_frequency VARCHAR(20) NULL COMMENT 'Fréquence contact au sol',
    radio_callsign VARCHAR(100) NULL COMMENT 'Indicatif contact radio',
    
    -- Ligne 3 : Nombre de patients par priorité (triage TCCC)
    patients_t1_urgent INT UNSIGNED DEFAULT 0 COMMENT 'T1 - Urgent chirurgical (1h)',
    patients_t2_urgent INT UNSIGNED DEFAULT 0 COMMENT 'T2 - Urgent (4h)',
    patients_t3_delayed INT UNSIGNED DEFAULT 0 COMMENT 'T3 - Différé (24h)',
    patients_t4_expectant INT UNSIGNED DEFAULT 0 COMMENT 'T4 - Expectant (soins palliatifs)',
    total_patients INT UNSIGNED GENERATED ALWAYS AS (patients_t1_urgent + patients_t2_urgent + patients_t3_delayed + patients_t4_expectant) STORED,
    
    -- Ligne 4 : Équipement spécial requis
    equipment_needed JSON NULL COMMENT 'Liste équipement : hoist, ventilator, etc.',
    
    -- Ligne 5 : Nombre de patients par type
    patients_litter INT UNSIGNED DEFAULT 0 COMMENT 'Patients brancard (couchés)',
    patients_ambulatory INT UNSIGNED DEFAULT 0 COMMENT 'Patients ambulatoires (debout)',
    
    -- Ligne 6 : Sécurité de la zone
    security_status ENUM('NO_ENEMY', 'POSSIBLE_ENEMY', 'ENEMY_IN_AREA', 'ENEMY_TROOPS', 'HOT_LZ') NOT NULL DEFAULT 'NO_ENEMY',
    enemy_description TEXT NULL COMMENT 'Description menace ennemie',
    
    -- Ligne 7 : Marquage LZ
    lz_marking ENUM('NONE', 'PANEL', 'PYRO', 'SMOKE', 'OTHER') DEFAULT 'NONE',
    lz_marking_color VARCHAR(50) NULL COMMENT 'Couleur du marquage',
    lz_marking_details TEXT NULL COMMENT 'Détails marquage',
    
    -- Ligne 8 : Nationalité et statut patients
    patient_nationality VARCHAR(100) DEFAULT 'FRIENDLY',
    patient_status ENUM('MILITARY', 'CIVILIAN', 'EPW', 'CHILD') DEFAULT 'MILITARY',
    
    -- Ligne 9 : NBC contamination
    nbc_contamination ENUM('NONE', 'NUCLEAR', 'BIOLOGICAL', 'CHEMICAL') DEFAULT 'NONE',
    nbc_details TEXT NULL,
    
    -- Informations complémentaires
    terrain_description TEXT NULL COMMENT 'Description terrain LZ',
    obstacles TEXT NULL COMMENT 'Obstacles notables',
    approach_direction VARCHAR(100) NULL COMMENT 'Direction approche recommandée',
    remarks TEXT NULL COMMENT 'Remarques additionnelles',
    
    -- Demandeur
    requested_by_user_id INT UNSIGNED NULL,
    requested_by_callsign VARCHAR(100) NULL,
    requested_by_unit VARCHAR(100) NULL,
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    -- Statut et workflow
    status ENUM('REQUESTED', 'ACKNOWLEDGED', 'ASSIGNED', 'INBOUND', 'ON_SITE', 'EVACUATING', 'COMPLETED', 'CANCELLED') DEFAULT 'REQUESTED',
    
    -- Asset assigné
    assigned_asset_callsign VARCHAR(100) NULL COMMENT 'Indicatif hélico assigné',
    assigned_pilot_user_id INT UNSIGNED NULL,
    assigned_at DATETIME NULL,
    
    -- Timeline
    acknowledged_at DATETIME NULL,
    eta DATETIME NULL COMMENT 'Estimated Time of Arrival',
    arrived_at DATETIME NULL,
    departed_at DATETIME NULL,
    completed_at DATETIME NULL,
    cancelled_at DATETIME NULL,
    cancellation_reason TEXT NULL,
    
    -- Golden hour tracking
    golden_hour_expires_at DATETIME NULL COMMENT 'Expiration golden hour pour T1',
    is_golden_hour_critical BOOLEAN GENERATED ALWAYS AS (
        patients_t1_urgent > 0 AND golden_hour_expires_at IS NOT NULL AND NOW() >= golden_hour_expires_at
    ) STORED,
    
    -- Métadonnées
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Index
    INDEX idx_tenant_context (tenant_id, context_id),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_requested_at (requested_at),
    INDEX idx_assigned_asset (assigned_asset_callsign),
    INDEX idx_golden_hour (golden_hour_expires_at, patients_t1_urgent),
    
    -- Contraintes
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (requested_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_pilot_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Demandes MEDEVAC 9-Line avec triage TCCC et workflow complet';

-- Table des patients dans une demande MEDEVAC
CREATE TABLE IF NOT EXISTS atak_medevac_patients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    medevac_request_id INT UNSIGNED NOT NULL,
    
    -- Identification patient
    patient_name VARCHAR(200) NULL COMMENT 'Nom si connu',
    patient_callsign VARCHAR(100) NULL,
    patient_unit VARCHAR(100) NULL,
    patient_steam_id VARCHAR(50) NULL,
    
    -- Triage TCCC
    triage_category ENUM('T1', 'T2', 'T3', 'T4') NOT NULL COMMENT 'Catégorie triage',
    triage_timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    triaged_by_callsign VARCHAR(100) NULL,
    
    -- État médical
    consciousness ENUM('ALERT', 'VERBAL', 'PAIN', 'UNRESPONSIVE') DEFAULT 'ALERT',
    breathing ENUM('NORMAL', 'ABNORMAL', 'ABSENT') DEFAULT 'NORMAL',
    circulation ENUM('NORMAL', 'COMPROMISED', 'ABSENT') DEFAULT 'NORMAL',
    
    -- Blessures principales
    injuries JSON NULL COMMENT 'Liste des blessures avec gravité',
    primary_injury TEXT NULL COMMENT 'Blessure principale',
    
    -- Soins administrés
    treatments_given JSON NULL COMMENT 'Liste des soins déjà donnés',
    medications_given JSON NULL COMMENT 'Médicaments administrés',
    
    -- Stabilisation
    is_stabilized BOOLEAN DEFAULT FALSE,
    stabilized_at DATETIME NULL,
    
    -- Transport
    requires_litter BOOLEAN DEFAULT FALSE COMMENT 'Besoin brancard',
    can_walk BOOLEAN DEFAULT FALSE COMMENT 'Peut marcher',
    
    -- Outcome
    evacuated BOOLEAN DEFAULT FALSE,
    evacuated_at DATETIME NULL,
    outcome ENUM('SURVIVED', 'KIA', 'DOW', 'UNKNOWN') NULL COMMENT 'Résultat final',
    
    -- Métadonnées
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Index
    INDEX idx_medevac_request (medevac_request_id),
    INDEX idx_triage_category (triage_category),
    INDEX idx_patient_steam (patient_steam_id),
    
    -- Contraintes
    FOREIGN KEY (medevac_request_id) REFERENCES atak_medevac_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Patients individuels dans demandes MEDEVAC avec état médical';

-- Table des mises à jour statut MEDEVAC
CREATE TABLE IF NOT EXISTS atak_medevac_status_updates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    medevac_request_id INT UNSIGNED NOT NULL,
    
    previous_status VARCHAR(50) NULL,
    new_status VARCHAR(50) NOT NULL,
    
    update_message TEXT NULL,
    updated_by_callsign VARCHAR(100) NULL,
    updated_by_user_id INT UNSIGNED NULL,
    
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    -- Index
    INDEX idx_medevac_request (medevac_request_id),
    INDEX idx_updated_at (updated_at),
    
    -- Contraintes
    FOREIGN KEY (medevac_request_id) REFERENCES atak_medevac_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Historique des changements de statut MEDEVAC';

-- Vue enrichie des demandes MEDEVAC actives
CREATE OR REPLACE VIEW v_atak_active_medevac AS
SELECT 
    m.*,
    requester_u.username AS requested_by_username,
    pilot_u.username AS assigned_pilot_username,
    CASE 
        WHEN m.golden_hour_expires_at IS NULL THEN NULL
        WHEN NOW() >= m.golden_hour_expires_at THEN 'EXPIRED'
        WHEN TIMESTAMPDIFF(MINUTE, NOW(), m.golden_hour_expires_at) <= 15 THEN 'CRITICAL'
        WHEN TIMESTAMPDIFF(MINUTE, NOW(), m.golden_hour_expires_at) <= 30 THEN 'WARNING'
        ELSE 'OK'
    END AS golden_hour_status,
    TIMESTAMPDIFF(MINUTE, NOW(), m.golden_hour_expires_at) AS golden_hour_minutes_remaining,
    (SELECT COUNT(*) FROM atak_medevac_patients WHERE medevac_request_id = m.id) AS actual_patient_count,
    (SELECT COUNT(*) FROM atak_medevac_patients WHERE medevac_request_id = m.id AND is_stabilized = TRUE) AS stabilized_patient_count,
    TIMESTAMPDIFF(MINUTE, m.requested_at, NOW()) AS minutes_since_request
FROM atak_medevac_requests m
LEFT JOIN users requester_u ON m.requested_by_user_id = requester_u.id
LEFT JOIN users pilot_u ON m.assigned_pilot_user_id = pilot_u.id
WHERE m.status IN ('REQUESTED', 'ACKNOWLEDGED', 'ASSIGNED', 'INBOUND', 'ON_SITE', 'EVACUATING');

-- Trigger pour mettre à jour le golden hour automatiquement
DELIMITER $$

CREATE TRIGGER trg_medevac_golden_hour
BEFORE INSERT ON atak_medevac_requests
FOR EACH ROW
BEGIN
    IF NEW.patients_t1_urgent > 0 AND NEW.golden_hour_expires_at IS NULL THEN
        SET NEW.golden_hour_expires_at = DATE_ADD(NEW.requested_at, INTERVAL 60 MINUTE);
    END IF;
END$$

DELIMITER ;

-- Trigger pour logger les changements de statut
DELIMITER $$

CREATE TRIGGER trg_medevac_status_log
AFTER UPDATE ON atak_medevac_requests
FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO atak_medevac_status_updates (medevac_request_id, previous_status, new_status, update_message)
        VALUES (NEW.id, OLD.status, NEW.status, CONCAT('Statut changé de ', OLD.status, ' à ', NEW.status));
    END IF;
END$$

DELIMITER ;
