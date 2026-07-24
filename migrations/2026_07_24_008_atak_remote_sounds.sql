-- Migration: Système sons à distance (Troll + Réaliste)
-- Date: 2026-07-24
-- Description: Permet au commandement de jouer des sons à distance sur les joueurs in-game

-- =====================================================
-- Table: atak_remote_sounds
-- Stocke les sons en attente d'être joués
-- =====================================================

CREATE TABLE IF NOT EXISTS atak_remote_sounds (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    
    -- Identification
    tenant_id INT UNSIGNED NOT NULL,
    context_id INT UNSIGNED NOT NULL,
    
    -- Type de son
    sound_type ENUM('troll', 'realistic') NOT NULL DEFAULT 'realistic',
    sound_id VARCHAR(100) NOT NULL COMMENT 'Identifiant du son (ex: airhorn, radio_static)',
    
    -- Ciblage
    target_type ENUM('player', 'unit', 'group', 'all', 'position') NOT NULL DEFAULT 'player',
    target_identifier VARCHAR(255) NULL COMMENT 'Callsign, Steam ID, ou nom groupe',
    
    -- Position (pour sons 3D réalistes)
    position_x DECIMAL(10,2) NULL COMMENT 'Coordonnée X Arma (optionnel)',
    position_y DECIMAL(10,2) NULL COMMENT 'Coordonnée Y Arma (optionnel)',
    position_z DECIMAL(10,2) NULL DEFAULT 0 COMMENT 'Altitude (optionnel)',
    
    -- Paramètres audio
    volume DECIMAL(3,2) NOT NULL DEFAULT 1.00 COMMENT '0.00 à 1.00',
    distance_audible INT UNSIGNED NOT NULL DEFAULT 100 COMMENT 'Distance en mètres (pour 3D)',
    
    -- Métadonnées
    triggered_by_user_id INT UNSIGNED NULL COMMENT 'Qui a déclenché le son',
    triggered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reason VARCHAR(500) NULL COMMENT 'Raison / contexte',
    
    -- État
    status ENUM('pending', 'delivered', 'failed', 'expired') NOT NULL DEFAULT 'pending',
    delivered_at DATETIME NULL,
    acknowledged_at DATETIME NULL COMMENT 'Quand le client a confirmé lecture',
    expires_at DATETIME NOT NULL COMMENT 'Expiration si non joué',
    
    -- Audit
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_tenant_context (tenant_id, context_id),
    INDEX idx_status_expires (status, expires_at),
    INDEX idx_target (target_type, target_identifier),
    INDEX idx_pending_recent (status, created_at),
    
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (context_id) REFERENCES contextes(id) ON DELETE CASCADE,
    FOREIGN KEY (triggered_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Sons à distance déclenchés depuis interface web';

-- =====================================================
-- Table: atak_sound_history
-- Historique des sons joués (analytics)
-- =====================================================

CREATE TABLE IF NOT EXISTS atak_sound_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    
    tenant_id INT UNSIGNED NOT NULL,
    context_id INT UNSIGNED NOT NULL,
    
    sound_id VARCHAR(100) NOT NULL,
    sound_type ENUM('troll', 'realistic') NOT NULL,
    
    target_identifier VARCHAR(255) NOT NULL,
    triggered_by_user_id INT UNSIGNED NULL,
    
    played_at DATETIME NOT NULL,
    acknowledged BOOLEAN NOT NULL DEFAULT FALSE,
    
    position_x DECIMAL(10,2) NULL,
    position_y DECIMAL(10,2) NULL,
    
    INDEX idx_tenant_context (tenant_id, context_id),
    INDEX idx_played_at (played_at),
    INDEX idx_sound_type (sound_type, sound_id),
    
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (context_id) REFERENCES contextes(id) ON DELETE CASCADE,
    FOREIGN KEY (triggered_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Historique sons joués (analytics et debug)';

-- =====================================================
-- Vue: Pend sounds récents par joueur
-- =====================================================

CREATE OR REPLACE VIEW v_atak_pending_sounds AS
SELECT 
    rs.id,
    rs.tenant_id,
    rs.context_id,
    rs.sound_type,
    rs.sound_id,
    rs.target_type,
    rs.target_identifier,
    rs.position_x,
    rs.position_y,
    rs.position_z,
    rs.volume,
    rs.distance_audible,
    rs.triggered_by_user_id,
    u.display_name AS triggered_by_username,
    rs.triggered_at,
    rs.reason,
    rs.status,
    rs.expires_at,
    TIMESTAMPDIFF(SECOND, NOW(), rs.expires_at) AS seconds_until_expiration,
    rs.created_at
FROM atak_remote_sounds rs
LEFT JOIN users u ON rs.triggered_by_user_id = u.id
WHERE rs.status = 'pending'
  AND rs.expires_at > NOW()
ORDER BY rs.created_at ASC;

-- =====================================================
-- Trigger: Nettoyage automatique sons expirés
-- =====================================================

DELIMITER //

CREATE TRIGGER IF NOT EXISTS trg_remote_sound_expire
BEFORE UPDATE ON atak_remote_sounds
FOR EACH ROW
BEGIN
    -- Marquer comme expiré si dépassé et toujours pending
    IF NEW.status = 'pending' AND NEW.expires_at < NOW() THEN
        SET NEW.status = 'expired';
    END IF;
END//

DELIMITER ;

-- =====================================================
-- Configuration: Paramètres sons par défaut
-- =====================================================

CREATE TABLE IF NOT EXISTS atak_sound_config (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    
    tenant_id INT UNSIGNED NOT NULL UNIQUE,
    
    -- Mode troll
    troll_mode_enabled BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Activer sons troll',
    troll_cooldown_seconds INT UNSIGNED NOT NULL DEFAULT 60 COMMENT 'Délai minimum entre sons troll',
    troll_max_per_hour INT UNSIGNED NOT NULL DEFAULT 10 COMMENT 'Maximum sons troll par heure',
    
    -- Permissions
    realistic_sounds_role VARCHAR(100) NULL COMMENT 'Rôle minimum pour sons réalistes (NULL = tous)',
    troll_sounds_role VARCHAR(100) NOT NULL DEFAULT 'admin' COMMENT 'Rôle minimum pour sons troll',
    
    -- Expiration par défaut
    default_expiration_seconds INT UNSIGNED NOT NULL DEFAULT 300 COMMENT '5 minutes par défaut',
    
    -- Audit
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Configuration système sons à distance par tenant';

-- =====================================================
-- Données exemple: Configuration par défaut
-- =====================================================

-- Note: À adapter selon ID tenant production
-- INSERT INTO atak_sound_config (tenant_id, troll_mode_enabled, troll_cooldown_seconds)
-- VALUES (1, FALSE, 60) ON DUPLICATE KEY UPDATE tenant_id = tenant_id;

-- =====================================================
-- Index optimisation polling
-- =====================================================

-- Index composite pour requête polling fréquente
CREATE INDEX idx_poll_optimization ON atak_remote_sounds 
    (tenant_id, context_id, status, target_identifier, expires_at);

-- =====================================================
-- Procédure: Cleanup automatique sons anciens
-- =====================================================

DELIMITER //

CREATE PROCEDURE IF NOT EXISTS sp_cleanup_old_remote_sounds()
BEGIN
    -- Archiver sons livrés > 24h dans historique
    INSERT INTO atak_sound_history 
        (tenant_id, context_id, sound_id, sound_type, target_identifier, 
         triggered_by_user_id, played_at, acknowledged, position_x, position_y)
    SELECT 
        tenant_id, context_id, sound_id, sound_type, target_identifier,
        triggered_by_user_id, delivered_at, 
        (acknowledged_at IS NOT NULL) AS acknowledged,
        position_x, position_y
    FROM atak_remote_sounds
    WHERE status IN ('delivered', 'expired', 'failed')
      AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR);
    
    -- Supprimer anciens sons traités
    DELETE FROM atak_remote_sounds
    WHERE status IN ('delivered', 'expired', 'failed')
      AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR);
    
    -- Supprimer historique > 30 jours
    DELETE FROM atak_sound_history
    WHERE played_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
END//

DELIMITER ;

-- =====================================================
-- Event scheduler: Nettoyage automatique quotidien
-- =====================================================

-- Activer event scheduler si pas déjà fait
SET GLOBAL event_scheduler = ON;

CREATE EVENT IF NOT EXISTS evt_cleanup_remote_sounds
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO CALL sp_cleanup_old_remote_sounds();

-- =====================================================
-- Commentaires finaux
-- =====================================================

/*
Usage API:

1. Déclencher son troll:
   POST /api/atak/sounds/trigger
   {
     "sound_type": "troll",
     "sound_id": "airhorn",
     "target_identifier": "ALPHA-1",
     "reason": "Bonne blague!"
   }

2. Déclencher son réaliste 3D:
   POST /api/atak/sounds/trigger
   {
     "sound_type": "realistic",
     "sound_id": "explosion_distant",
     "position": [15234.56, 8765.43, 0],
     "distance_audible": 500,
     "target_type": "all"
   }

3. Polling client (mod):
   GET /api/atak/sounds/pending?callsign=ALPHA-1&steam_id=xxx

4. Acknowledge (mod):
   POST /api/atak/sounds/ack
   {
     "sound_id": 123
   }
*/
