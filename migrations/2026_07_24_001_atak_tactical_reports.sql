-- Migration : Système de rapports tactiques structurés
-- Ajout des tables pour SPOTREP, SITREP, SALUTE, CONTACT

-- Table principale des rapports tactiques
CREATE TABLE IF NOT EXISTS atak_tactical_reports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    context_id INT UNSIGNED NOT NULL COMMENT 'Contexte opérationnel (mission/serveur)',
    
    -- Identification
    report_type ENUM('SPOTREP', 'SITREP', 'SALUTE', 'CONTACT', 'OTHER') NOT NULL,
    report_number VARCHAR(50) NULL COMMENT 'Numéro séquentiel du rapport (ex: SPOTREP-001)',
    priority ENUM('ROUTINE', 'PRIORITY', 'IMMEDIATE', 'FLASH') DEFAULT 'ROUTINE',
    classification ENUM('UNCLASSIFIED', 'RESTRICTED', 'CONFIDENTIAL', 'SECRET') DEFAULT 'UNCLASSIFIED',
    
    -- Émetteur
    submitter_user_id INT UNSIGNED NULL COMMENT 'Utilisateur ayant soumis le rapport',
    submitter_callsign VARCHAR(100) NULL COMMENT 'Indicatif de l\'émetteur',
    submitter_unit VARCHAR(100) NULL COMMENT 'Unité de l\'émetteur',
    submitter_steam_id VARCHAR(50) NULL,
    
    -- Localisation
    pos_x DECIMAL(12, 4) NULL COMMENT 'Position X monde Arma',
    pos_y DECIMAL(12, 4) NULL COMMENT 'Position Y monde Arma',
    grid_reference VARCHAR(50) NULL COMMENT 'Référence grille militaire',
    location_description TEXT NULL COMMENT 'Description textuelle du lieu',
    
    -- Temporalité
    dtg VARCHAR(50) NULL COMMENT 'Date-Time Group (format militaire)',
    report_timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Horodatage du rapport',
    event_timestamp DATETIME NULL COMMENT 'Horodatage de l\'événement observé',
    
    -- Contenu structuré (JSON selon type de rapport)
    structured_data JSON NULL COMMENT 'Données structurées spécifiques au type de rapport',
    
    -- Contenu textuel
    summary TEXT NULL COMMENT 'Résumé court du rapport',
    details TEXT NULL COMMENT 'Détails complets du rapport',
    remarks TEXT NULL COMMENT 'Remarques additionnelles',
    
    -- Attachements
    has_attachments BOOLEAN DEFAULT FALSE,
    attachment_count INT UNSIGNED DEFAULT 0,
    
    -- Statut et workflow
    status ENUM('DRAFT', 'SUBMITTED', 'ACKNOWLEDGED', 'ACTIONED', 'ARCHIVED') DEFAULT 'SUBMITTED',
    acknowledged_by_user_id INT UNSIGNED NULL,
    acknowledged_at DATETIME NULL,
    action_taken TEXT NULL COMMENT 'Actions prises suite au rapport',
    
    -- Visibilité et diffusion
    visibility ENUM('ALL', 'COMMAND_ONLY', 'RESTRICTED') DEFAULT 'ALL',
    distributed_to JSON NULL COMMENT 'Liste des unités/rôles destinataires',
    
    -- Métadonnées
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    
    -- Index
    INDEX idx_tenant_context (tenant_id, context_id),
    INDEX idx_report_type (report_type),
    INDEX idx_priority (priority),
    INDEX idx_submitter (submitter_user_id, submitter_steam_id),
    INDEX idx_timestamp (report_timestamp),
    INDEX idx_status (status),
    INDEX idx_position (pos_x, pos_y),
    
    -- Contraintes
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (submitter_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (acknowledged_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Rapports tactiques structurés : SPOTREP, SITREP, SALUTE, CONTACT';

-- Table des attachements de rapports (photos, documents)
CREATE TABLE IF NOT EXISTS atak_report_attachments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_id INT UNSIGNED NOT NULL,
    
    -- Fichier
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(50) NULL COMMENT 'Type MIME',
    file_size INT UNSIGNED NULL COMMENT 'Taille en octets',
    original_filename VARCHAR(255) NULL,
    
    -- Métadonnées
    caption TEXT NULL COMMENT 'Légende de l\'attachement',
    taken_at DATETIME NULL COMMENT 'Horodatage de capture',
    
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    -- Index
    INDEX idx_report (report_id),
    
    -- Contraintes
    FOREIGN KEY (report_id) REFERENCES atak_tactical_reports(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Attachements des rapports tactiques';

-- Vue pour faciliter les requêtes de rapports avec émetteur
CREATE OR REPLACE VIEW v_atak_tactical_reports AS
SELECT 
    r.*,
    u.username AS submitter_username,
    u.first_name AS submitter_first_name,
    u.last_name AS submitter_last_name,
    COALESCE(r.submitter_callsign, CONCAT(u.first_name, ' ', u.last_name)) AS display_name,
    ack_u.username AS acknowledged_by_username,
    (SELECT COUNT(*) FROM atak_report_attachments WHERE report_id = r.id) AS actual_attachment_count
FROM atak_tactical_reports r
LEFT JOIN users u ON r.submitter_user_id = u.id
LEFT JOIN users ack_u ON r.acknowledged_by_user_id = ack_u.id
WHERE r.deleted_at IS NULL;

-- Table de configuration des templates de rapports par tenant
CREATE TABLE IF NOT EXISTS atak_report_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    
    report_type ENUM('SPOTREP', 'SITREP', 'SALUTE', 'CONTACT', 'OTHER') NOT NULL,
    template_name VARCHAR(100) NOT NULL,
    
    -- Configuration du template (JSON)
    field_config JSON NULL COMMENT 'Configuration des champs : requis, optionnels, valeurs par défaut',
    
    is_active BOOLEAN DEFAULT TRUE,
    is_default BOOLEAN DEFAULT FALSE COMMENT 'Template par défaut pour ce type',
    
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Index
    INDEX idx_tenant_type (tenant_id, report_type),
    UNIQUE KEY uk_tenant_type_name (tenant_id, report_type, template_name),
    
    -- Contraintes
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Templates de configuration des rapports tactiques par tenant';
