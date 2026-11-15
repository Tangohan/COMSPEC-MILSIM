-- Actions sur les téléphones en jeu depuis le poste TOC web (notifier, faire sonner, localiser, trianguler).
-- Équivalent MySQL de bootstrap/atak_toc_phone_migration.php (exécuté par l'étape 4760). Aucune donnée existante lue ni
-- modifiée. Sans ces tables, la fonction reste désactivée et le poste le dit. Les ordres vers le jeu passent par la file
-- existante atak_web_commands (genre « phone »), qui n'est pas modifiée.
-- Retour arrière : migrations/rollback/atak_toc_phone_down.sql.

CREATE TABLE IF NOT EXISTS atak_toc_phone_settings (
  tenant_id INT UNSIGNED NOT NULL,
  enabled TINYINT NOT NULL DEFAULT 0 COMMENT 'fonction active pour la communauté (sans ligne : désactivée)',
  hide_hostile_journal TINYINT NOT NULL DEFAULT 0 COMMENT '1 : pas d''entrée « localisé par le TOC » sur les téléphones hostiles ; jamais pour un allié',
  updated_by INT UNSIGNED DEFAULT NULL,
  updated_at DATETIME DEFAULT NULL,
  PRIMARY KEY (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS atak_toc_phone_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  map_id INT UNSIGNED NOT NULL DEFAULT 1,
  user_id INT UNSIGNED DEFAULT NULL COMMENT 'opérateur du poste',
  operator VARCHAR(60) NOT NULL DEFAULT '',
  target_ref VARCHAR(40) NOT NULL COMMENT 'référence opaque du téléphone (jamais un compte Steam)',
  target_label VARCHAR(80) NOT NULL DEFAULT '',
  target_kind VARCHAR(8) NOT NULL DEFAULT 'player' COMMENT 'player|npc',
  action VARCHAR(16) NOT NULL COMMENT 'notify|ring|ring_stop|locate|triangulate',
  level VARCHAR(8) DEFAULT NULL COMMENT 'info|alert|urgent (notifier)',
  message VARCHAR(200) DEFAULT NULL,
  ack_requested TINYINT NOT NULL DEFAULT 0,
  command_id INT UNSIGNED DEFAULT NULL COMMENT 'commande de la file atak_web_commands',
  status VARCHAR(12) NOT NULL DEFAULT 'queued' COMMENT 'queued|delivered|done|failed|expired|cancelled|refused|running',
  result_text VARCHAR(255) NOT NULL DEFAULT '',
  pos_x DOUBLE DEFAULT NULL COMMENT 'mètres de jeu',
  pos_y DOUBLE DEFAULT NULL,
  accuracy_m DOUBLE DEFAULT NULL,
  detail_json MEDIUMTEXT DEFAULT NULL,
  ready_at DATETIME DEFAULT NULL COMMENT 'triangulation : instant où le résultat est livré',
  expires_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_atak_toc_phone_events_tenant (tenant_id, created_at),
  KEY idx_atak_toc_phone_events_target (tenant_id, target_ref, action, created_at),
  KEY idx_atak_toc_phone_events_operator (tenant_id, user_id, action, created_at),
  KEY idx_atak_toc_phone_events_command (tenant_id, command_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
