-- Migration : Enrichissements intelligence tactique (Peaufinage features Phase 1 & 2)
-- Ajout de calculs automatiques, scoring, routage intelligent, prédictions

-- ============================================
-- 1. RAPPORTS TACTIQUES : Auto-routing et distribution intelligente
-- ============================================

-- Table de routage automatique des rapports
CREATE TABLE IF NOT EXISTS atak_report_routing_rules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    
    rule_name VARCHAR(200) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    priority_order INT UNSIGNED DEFAULT 100 COMMENT 'Ordre d\'exécution (plus petit = prioritaire)',
    
    -- Conditions de déclenchement (JSON)
    trigger_conditions JSON NOT NULL COMMENT 'Conditions : report_type, priority, keywords, zone, etc.',
    
    -- Actions de routage
    auto_assign_to_roles JSON NULL COMMENT 'Rôles destinataires automatiques',
    auto_assign_to_users JSON NULL COMMENT 'Utilisateurs spécifiques',
    auto_assign_to_units JSON NULL COMMENT 'Unités destinataires',
    
    -- Notifications
    send_notification BOOLEAN DEFAULT TRUE,
    notification_channels JSON NULL COMMENT 'Canaux : in-game, email, webhook, discord',
    notification_template TEXT NULL,
    
    -- Escalade
    escalate_after_minutes INT UNSIGNED NULL COMMENT 'Escalader si non traité après X minutes',
    escalate_to_roles JSON NULL,
    
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_tenant (tenant_id),
    INDEX idx_active_priority (is_active, priority_order),
    
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Règles de routage automatique des rapports tactiques';

-- Table d\'historique de routage
CREATE TABLE IF NOT EXISTS atak_report_routing_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_id INT UNSIGNED NOT NULL,
    routing_rule_id INT UNSIGNED NULL,
    
    routed_to_type ENUM('USER', 'ROLE', 'UNIT') NOT NULL,
    routed_to_identifier VARCHAR(100) NOT NULL COMMENT 'ID ou nom du destinataire',
    
    notification_sent BOOLEAN DEFAULT FALSE,
    notification_channel VARCHAR(50) NULL,
    notification_sent_at DATETIME NULL,
    
    acknowledged BOOLEAN DEFAULT FALSE,
    acknowledged_by_user_id INT UNSIGNED NULL,
    acknowledged_at DATETIME NULL,
    
    routed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_report (report_id),
    INDEX idx_routed_to (routed_to_type, routed_to_identifier),
    INDEX idx_notification_status (notification_sent, acknowledged),
    
    FOREIGN KEY (report_id) REFERENCES atak_tactical_reports(id) ON DELETE CASCADE,
    FOREIGN KEY (routing_rule_id) REFERENCES atak_report_routing_rules(id) ON DELETE SET NULL,
    FOREIGN KEY (acknowledged_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Historique de routage et notifications des rapports';

-- ============================================
-- 2. ZONES TACTIQUES : Calcul menace et notifications temps réel
-- ============================================

-- Extension table zones avec scoring de menace
ALTER TABLE atak_tactical_zones 
ADD COLUMN threat_score DECIMAL(5, 2) NULL COMMENT 'Score de menace calculé (0-100)',
ADD COLUMN threat_last_updated DATETIME NULL COMMENT 'Dernière mise à jour score menace',
ADD COLUMN threat_factors JSON NULL COMMENT 'Facteurs contributifs au score',
ADD COLUMN nearby_threats_count INT UNSIGNED DEFAULT 0 COMMENT 'Nombre menaces à proximité';

-- Table d\'événements dans les zones (pour calcul menace)
CREATE TABLE IF NOT EXISTS atak_zone_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    zone_id INT UNSIGNED NOT NULL,
    tenant_id INT UNSIGNED NOT NULL,
    context_id INT UNSIGNED NOT NULL,
    
    event_type ENUM(
        'CONTACT_ENEMY', 'FIRE_TAKEN', 'IED_EXPLOSION', 'CASUALTY',
        'POI_DISCOVERED', 'ACTIVITY_SUSPICIOUS', 'UNIT_AMBUSHED',
        'INTEL_REPORT', 'DRONE_DETECTION', 'OTHER'
    ) NOT NULL,
    
    event_severity ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL') DEFAULT 'MEDIUM',
    event_description TEXT NULL,
    
    -- Position exacte dans la zone
    event_pos_x DECIMAL(12, 4) NULL,
    event_pos_y DECIMAL(12, 4) NULL,
    
    -- Impact sur menace
    threat_impact DECIMAL(5, 2) DEFAULT 0.0 COMMENT 'Impact sur score menace (-100 à +100)',
    
    -- Émetteur
    reported_by_user_id INT UNSIGNED NULL,
    reported_by_callsign VARCHAR(100) NULL,
    
    -- Temporel
    event_occurred_at DATETIME NULL COMMENT 'Quand événement s\'est produit',
    reported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    -- Decay (obsolescence)
    is_active BOOLEAN DEFAULT TRUE,
    expires_at DATETIME NULL COMMENT 'Expiration auto de l\'événement',
    
    INDEX idx_zone (zone_id),
    INDEX idx_tenant_context (tenant_id, context_id),
    INDEX idx_event_type (event_type),
    INDEX idx_event_occurred (event_occurred_at),
    INDEX idx_active_expires (is_active, expires_at),
    
    FOREIGN KEY (zone_id) REFERENCES atak_tactical_zones(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (reported_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Événements tactiques dans les zones pour calcul menace dynamique';

-- Table de notifications temps réel
CREATE TABLE IF NOT EXISTS atak_realtime_notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    context_id INT UNSIGNED NOT NULL,
    
    notification_type ENUM(
        'ZONE_THREAT_INCREASE', 'ZONE_ENTRY_WARNING', 'MEDEVAC_CRITICAL',
        'QRF_REQUIRED', 'VEHICLE_CRITICAL', 'POI_CORRELATION', 'REPORT_URGENT',
        'GOLDEN_HOUR_WARNING', 'ASSET_UNAVAILABLE', 'OTHER'
    ) NOT NULL,
    
    priority ENUM('INFO', 'LOW', 'MEDIUM', 'HIGH', 'CRITICAL') DEFAULT 'INFO',
    
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    
    -- Référence à l\'entité source
    source_entity_type ENUM('REPORT', 'POI', 'ZONE', 'MEDEVAC', 'QRF', 'VEHICLE', 'OTHER') NULL,
    source_entity_id INT UNSIGNED NULL,
    
    -- Destinataires
    target_roles JSON NULL COMMENT 'Rôles ciblés',
    target_users JSON NULL COMMENT 'Utilisateurs ciblés',
    target_units JSON NULL COMMENT 'Unités ciblées',
    
    -- Son et affichage
    sound_alert VARCHAR(100) NULL,
    show_on_map BOOLEAN DEFAULT FALSE,
    map_pos_x DECIMAL(12, 4) NULL,
    map_pos_y DECIMAL(12, 4) NULL,
    
    -- Statut
    is_active BOOLEAN DEFAULT TRUE,
    expires_at DATETIME NULL COMMENT 'Expiration automatique',
    
    -- Métadonnées
    properties JSON NULL,
    
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_tenant_context (tenant_id, context_id),
    INDEX idx_notification_type (notification_type),
    INDEX idx_priority (priority),
    INDEX idx_active_expires (is_active, expires_at),
    INDEX idx_created_at (created_at),
    
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Notifications temps réel pour tous événements tactiques critiques';

-- ============================================
-- 3. MEDEVAC : Scoring urgence et prédiction ETA
-- ============================================

-- Extension table MEDEVAC avec scoring intelligent
ALTER TABLE atak_medevac_requests
ADD COLUMN urgency_score DECIMAL(5, 2) NULL COMMENT 'Score urgence calculé (0-100)',
ADD COLUMN urgency_factors JSON NULL COMMENT 'Facteurs contributifs au score',
ADD COLUMN estimated_response_time_minutes INT UNSIGNED NULL COMMENT 'Temps réponse estimé',
ADD COLUMN nearest_available_asset VARCHAR(100) NULL COMMENT 'Asset le plus proche disponible',
ADD COLUMN pickup_zone_threat_level VARCHAR(20) NULL COMMENT 'Niveau menace zone pickup',
ADD COLUMN weather_impact ENUM('NONE', 'MINOR', 'MODERATE', 'SEVERE') DEFAULT 'NONE',
ADD COLUMN flight_risk_assessment TEXT NULL COMMENT 'Évaluation risques vol';

-- Table des assets médicaux disponibles
CREATE TABLE IF NOT EXISTS atak_medical_assets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    context_id INT UNSIGNED NOT NULL,
    
    asset_callsign VARCHAR(100) NOT NULL,
    asset_type ENUM('MEDEVAC_HELO', 'CASEVAC_HELO', 'AMBULANCE', 'FIELD_HOSPITAL') NOT NULL,
    
    -- Capacités
    max_litter_patients INT UNSIGNED DEFAULT 2,
    max_ambulatory_patients INT UNSIGNED DEFAULT 4,
    has_advanced_equipment BOOLEAN DEFAULT FALSE COMMENT 'Équipement médical avancé',
    capabilities JSON NULL COMMENT 'Capacités spécifiques : hoist, ventilator, etc.',
    
    -- Position actuelle
    current_pos_x DECIMAL(12, 4) NULL,
    current_pos_y DECIMAL(12, 4) NULL,
    current_location VARCHAR(200) NULL,
    
    -- Statut
    status ENUM('AVAILABLE', 'ASSIGNED', 'INBOUND', 'ON_SCENE', 'EVACUATING', 'RTB', 'MAINTENANCE', 'UNAVAILABLE') DEFAULT 'AVAILABLE',
    assigned_to_medevac_id INT UNSIGNED NULL,
    
    -- Performance
    average_response_time_minutes INT UNSIGNED NULL COMMENT 'Temps réponse moyen',
    cruise_speed_kph INT UNSIGNED DEFAULT 200 COMMENT 'Vitesse croisière',
    max_range_km INT UNSIGNED DEFAULT 300 COMMENT 'Autonomie max',
    
    -- Équipage
    pilot_callsign VARCHAR(100) NULL,
    pilot_user_id INT UNSIGNED NULL,
    medic_callsign VARCHAR(100) NULL,
    crew_count INT UNSIGNED DEFAULT 2,
    
    last_seen_at DATETIME NULL,
    
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_tenant_context (tenant_id, context_id),
    INDEX idx_status (status),
    INDEX idx_asset_type (asset_type),
    INDEX idx_current_position (current_pos_x, current_pos_y),
    UNIQUE KEY uk_tenant_context_callsign (tenant_id, context_id, asset_callsign),
    
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to_medevac_id) REFERENCES atak_medevac_requests(id) ON DELETE SET NULL,
    FOREIGN KEY (pilot_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Assets médicaux disponibles avec capacités et statut';

-- ============================================
-- 4. QRF : Optimisation route et gestion multiple QRF
-- ============================================

-- Extension table QRF avec optimisation
ALTER TABLE atak_qrf_requests
ADD COLUMN optimal_route_calculated BOOLEAN DEFAULT FALSE,
ADD COLUMN route_waypoints JSON NULL COMMENT 'Waypoints route optimisée',
ADD COLUMN route_distance_meters DECIMAL(10, 2) NULL COMMENT 'Distance totale route',
ADD COLUMN route_estimated_time_minutes INT UNSIGNED NULL COMMENT 'Temps estimé trajet',
ADD COLUMN route_hazards JSON NULL COMMENT 'Dangers identifiés sur route',
ADD COLUMN alternate_routes JSON NULL COMMENT 'Routes alternatives',
ADD COLUMN reinforcement_needed BOOLEAN DEFAULT FALSE COMMENT 'Renfort additionnel requis';

-- Table de coordination multi-QRF
CREATE TABLE IF NOT EXISTS atak_qrf_coordination (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    context_id INT UNSIGNED NOT NULL,
    
    coordination_name VARCHAR(200) NOT NULL COMMENT 'Nom opération coordination',
    
    -- QRF primaire
    primary_qrf_id INT UNSIGNED NOT NULL,
    
    -- QRF secondaires
    secondary_qrf_ids JSON NULL COMMENT 'IDs des QRF secondaires',
    
    -- Plan de coordination
    coordination_type ENUM('CONVERGING', 'FLANKING', 'BLOCKING', 'SEQUENTIAL', 'PINCER') NOT NULL,
    coordination_plan TEXT NULL COMMENT 'Plan tactique coordination',
    
    -- Timing
    synchronize_arrival BOOLEAN DEFAULT TRUE,
    target_arrival_time DATETIME NULL,
    
    -- Communication
    common_frequency VARCHAR(20) NULL COMMENT 'Fréquence commune QRF',
    command_callsign VARCHAR(100) NULL COMMENT 'Indicatif commandement',
    
    -- Statut
    status ENUM('PLANNED', 'EXECUTING', 'ENGAGED', 'SUCCESSFUL', 'FAILED', 'CANCELLED') DEFAULT 'PLANNED',
    
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_tenant_context (tenant_id, context_id),
    INDEX idx_primary_qrf (primary_qrf_id),
    INDEX idx_status (status),
    
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (primary_qrf_id) REFERENCES atak_qrf_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Coordination de multiple QRF pour opérations complexes';

-- ============================================
-- 5. VÉHICULES : Prédiction panne et maintenance préventive
-- ============================================

-- Extension table véhicules avec prédictions
ALTER TABLE atak_vehicle_tracking
ADD COLUMN maintenance_score DECIMAL(5, 2) NULL COMMENT 'Score santé globale (0-100)',
ADD COLUMN failure_risk ENUM('NONE', 'LOW', 'MEDIUM', 'HIGH', 'CRITICAL') DEFAULT 'NONE',
ADD COLUMN predicted_failure_time DATETIME NULL COMMENT 'Heure prédite panne prochaine',
ADD COLUMN recommended_maintenance TEXT NULL COMMENT 'Maintenance recommandée',
ADD COLUMN total_distance_traveled DECIMAL(12, 2) DEFAULT 0.0 COMMENT 'Distance totale parcourue (km)',
ADD COLUMN total_operating_hours DECIMAL(10, 2) DEFAULT 0.0 COMMENT 'Heures opération totales',
ADD COLUMN fuel_consumption_rate DECIMAL(6, 2) NULL COMMENT 'Consommation L/100km',
ADD COLUMN last_maintenance_at DATETIME NULL,
ADD COLUMN next_maintenance_due_at DATETIME NULL;

-- Table d\'historique maintenance
CREATE TABLE IF NOT EXISTS atak_vehicle_maintenance_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vehicle_tracking_id INT UNSIGNED NOT NULL,
    
    maintenance_type ENUM('SCHEDULED', 'PREVENTIVE', 'CORRECTIVE', 'EMERGENCY') NOT NULL,
    maintenance_category ENUM('ENGINE', 'TRANSMISSION', 'SUSPENSION', 'WEAPONS', 'ELECTRONICS', 'GENERAL') NOT NULL,
    
    description TEXT NOT NULL,
    parts_replaced JSON NULL,
    work_performed TEXT NULL,
    
    -- Personnel
    performed_by_callsign VARCHAR(100) NULL,
    performed_by_user_id INT UNSIGNED NULL,
    
    -- Temps et coût
    maintenance_duration_minutes INT UNSIGNED NULL,
    downtime_minutes INT UNSIGNED NULL COMMENT 'Temps hors service',
    
    -- Avant/après
    condition_before VARCHAR(50) NULL,
    condition_after VARCHAR(50) NULL,
    health_improvement DECIMAL(5, 2) NULL COMMENT 'Amélioration santé en %',
    
    performed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_vehicle (vehicle_tracking_id),
    INDEX idx_maintenance_type (maintenance_type),
    INDEX idx_performed_at (performed_at),
    
    FOREIGN KEY (vehicle_tracking_id) REFERENCES atak_vehicle_tracking(id) ON DELETE CASCADE,
    FOREIGN KEY (performed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Historique maintenance préventive et corrective véhicules';

-- ============================================
-- 6. POI : Corrélation intelligente et confidence scoring
-- ============================================

-- Extension table POI avec scoring
ALTER TABLE atak_poi
ADD COLUMN confidence_score DECIMAL(5, 2) DEFAULT 50.0 COMMENT 'Score confiance (0-100)',
ADD COLUMN correlation_count INT UNSIGNED DEFAULT 0 COMMENT 'Nombre corrélations avec autres POI',
ADD COLUMN last_updated_confidence DATETIME NULL,
ADD COLUMN intel_quality ENUM('UNVERIFIED', 'LOW', 'MEDIUM', 'HIGH', 'CONFIRMED') DEFAULT 'UNVERIFIED',
ADD COLUMN verification_status ENUM('PENDING', 'VERIFIED', 'DISPROVEN', 'OUTDATED') DEFAULT 'PENDING',
ADD COLUMN pattern_detected VARCHAR(200) NULL COMMENT 'Pattern tactique détecté';

-- Table de corrélations entre POI
CREATE TABLE IF NOT EXISTS atak_poi_correlations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    context_id INT UNSIGNED NOT NULL,
    
    poi_id_1 INT UNSIGNED NOT NULL,
    poi_id_2 INT UNSIGNED NOT NULL,
    
    correlation_type ENUM(
        'PROXIMITY', 'TEMPORAL', 'ACTIVITY_PATTERN', 'ENTITY_RELATED',
        'SUPPLY_CHAIN', 'COMMAND_STRUCTURE', 'NETWORK', 'OTHER'
    ) NOT NULL,
    
    correlation_strength DECIMAL(5, 2) NOT NULL COMMENT 'Force corrélation (0-100)',
    correlation_explanation TEXT NULL COMMENT 'Explication corrélation',
    
    -- Impact sur intelligence
    intel_value ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL') DEFAULT 'MEDIUM',
    recommended_action TEXT NULL COMMENT 'Action recommandée',
    
    -- Détection
    detected_by ENUM('AUTOMATIC', 'MANUAL', 'AI_ANALYSIS') DEFAULT 'AUTOMATIC',
    detected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    -- Validation
    validated BOOLEAN DEFAULT FALSE,
    validated_by_user_id INT UNSIGNED NULL,
    validated_at DATETIME NULL,
    
    is_active BOOLEAN DEFAULT TRUE,
    
    INDEX idx_tenant_context (tenant_id, context_id),
    INDEX idx_poi1 (poi_id_1),
    INDEX idx_poi2 (poi_id_2),
    INDEX idx_correlation_type (correlation_type),
    INDEX idx_correlation_strength (correlation_strength),
    INDEX idx_active (is_active),
    UNIQUE KEY uk_poi_pair (poi_id_1, poi_id_2, correlation_type),
    
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (poi_id_1) REFERENCES atak_poi(id) ON DELETE CASCADE,
    FOREIGN KEY (poi_id_2) REFERENCES atak_poi(id) ON DELETE CASCADE,
    FOREIGN KEY (validated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Corrélations intelligentes entre POI pour analyse pattern';

-- Table d\'analyse d\'intelligence agrégée
CREATE TABLE IF NOT EXISTS atak_intelligence_analysis (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    context_id INT UNSIGNED NOT NULL,
    
    analysis_type ENUM('PATTERN_DETECTION', 'THREAT_ASSESSMENT', 'NETWORK_MAPPING', 'PREDICTION', 'OTHER') NOT NULL,
    analysis_name VARCHAR(200) NOT NULL,
    
    -- Entités analysées
    analyzed_pois JSON NULL COMMENT 'IDs des POI analysés',
    analyzed_reports JSON NULL COMMENT 'IDs des rapports analysés',
    analyzed_zones JSON NULL COMMENT 'IDs des zones analysées',
    
    -- Résultats
    findings TEXT NOT NULL COMMENT 'Découvertes principales',
    confidence_level DECIMAL(5, 2) NOT NULL COMMENT 'Niveau confiance analyse',
    
    -- Recommandations
    recommended_actions JSON NULL,
    priority ENUM('INFO', 'LOW', 'MEDIUM', 'HIGH', 'CRITICAL') DEFAULT 'MEDIUM',
    
    -- Métadonnées
    analysis_method VARCHAR(100) NULL COMMENT 'Méthode analyse utilisée',
    performed_by_user_id INT UNSIGNED NULL,
    performed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    is_active BOOLEAN DEFAULT TRUE,
    expires_at DATETIME NULL COMMENT 'Expiration pertinence analyse',
    
    INDEX idx_tenant_context (tenant_id, context_id),
    INDEX idx_analysis_type (analysis_type),
    INDEX idx_priority (priority),
    INDEX idx_performed_at (performed_at),
    
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (performed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Analyses d\'intelligence agrégée pour décision tactique';

-- ============================================
-- VUES ENRICHIES
-- ============================================

-- Vue rapports avec routage et statut notifications
CREATE OR REPLACE VIEW v_atak_reports_enriched AS
SELECT 
    r.*,
    (SELECT COUNT(*) FROM atak_report_routing_history WHERE report_id = r.id) AS routing_count,
    (SELECT COUNT(*) FROM atak_report_routing_history WHERE report_id = r.id AND acknowledged = FALSE) AS pending_acknowledgments,
    (SELECT GROUP_CONCAT(DISTINCT routed_to_identifier SEPARATOR ', ') 
     FROM atak_report_routing_history 
     WHERE report_id = r.id AND routed_to_type = 'UNIT') AS routed_units
FROM atak_tactical_reports r
WHERE r.deleted_at IS NULL;

-- Vue zones avec menace calculée
CREATE OR REPLACE VIEW v_atak_zones_threat_assessed AS
SELECT 
    z.*,
    (SELECT COUNT(*) FROM atak_zone_events 
     WHERE zone_id = z.id AND is_active = TRUE AND event_severity IN ('HIGH', 'CRITICAL')) AS critical_events_count,
    (SELECT AVG(threat_impact) FROM atak_zone_events 
     WHERE zone_id = z.id AND is_active = TRUE) AS avg_threat_impact,
    CASE 
        WHEN z.threat_score >= 80 THEN 'CRITICAL'
        WHEN z.threat_score >= 60 THEN 'HIGH'
        WHEN z.threat_score >= 40 THEN 'MEDIUM'
        WHEN z.threat_score >= 20 THEN 'LOW'
        ELSE 'MINIMAL'
    END AS threat_assessment_label
FROM atak_tactical_zones z
WHERE z.deleted_at IS NULL;

-- Vue MEDEVAC avec asset optimal et prédictions
CREATE OR REPLACE VIEW v_atak_medevac_optimized AS
SELECT 
    m.*,
    ma.asset_callsign AS nearest_asset_callsign,
    ma.asset_type AS nearest_asset_type,
    ma.status AS nearest_asset_status,
    CASE 
        WHEN m.urgency_score >= 90 THEN 'EXTREME'
        WHEN m.urgency_score >= 75 THEN 'CRITICAL'
        WHEN m.urgency_score >= 50 THEN 'HIGH'
        WHEN m.urgency_score >= 25 THEN 'MEDIUM'
        ELSE 'LOW'
    END AS urgency_assessment,
    TIMESTAMPDIFF(MINUTE, m.requested_at, NOW()) AS wait_time_minutes
FROM atak_medevac_requests m
LEFT JOIN atak_medical_assets ma ON m.nearest_available_asset = ma.asset_callsign 
    AND ma.tenant_id = m.tenant_id AND ma.context_id = m.context_id
WHERE m.status IN ('REQUESTED', 'ACKNOWLEDGED', 'ASSIGNED', 'INBOUND', 'ON_SITE', 'EVACUATING');

-- Vue véhicules avec prédiction maintenance
CREATE OR REPLACE VIEW v_atak_vehicles_predictive AS
SELECT 
    v.*,
    CASE 
        WHEN v.maintenance_score >= 80 THEN 'EXCELLENT'
        WHEN v.maintenance_score >= 60 THEN 'GOOD'
        WHEN v.maintenance_score >= 40 THEN 'FAIR'
        WHEN v.maintenance_score >= 20 THEN 'POOR'
        ELSE 'CRITICAL'
    END AS health_status_label,
    (SELECT COUNT(*) FROM atak_vehicle_maintenance_log WHERE vehicle_tracking_id = v.id) AS maintenance_count,
    (SELECT MAX(performed_at) FROM atak_vehicle_maintenance_log WHERE vehicle_tracking_id = v.id) AS last_maintenance_date,
    DATEDIFF(NOW(), v.last_maintenance_at) AS days_since_maintenance
FROM atak_vehicle_tracking v;

-- Vue POI avec corrélations
CREATE OR REPLACE VIEW v_atak_poi_intelligence AS
SELECT 
    p.*,
    (SELECT COUNT(*) FROM atak_poi_correlations 
     WHERE (poi_id_1 = p.id OR poi_id_2 = p.id) AND is_active = TRUE) AS active_correlations_count,
    (SELECT AVG(correlation_strength) FROM atak_poi_correlations 
     WHERE (poi_id_1 = p.id OR poi_id_2 = p.id) AND is_active = TRUE) AS avg_correlation_strength,
    CASE 
        WHEN p.confidence_score >= 90 THEN 'CONFIRMED'
        WHEN p.confidence_score >= 70 THEN 'HIGH_CONFIDENCE'
        WHEN p.confidence_score >= 50 THEN 'MEDIUM_CONFIDENCE'
        WHEN p.confidence_score >= 30 THEN 'LOW_CONFIDENCE'
        ELSE 'UNVERIFIED'
    END AS confidence_label
FROM atak_poi p
WHERE p.deleted_at IS NULL;
