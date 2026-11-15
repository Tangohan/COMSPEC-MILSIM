-- Sessions de jeu des joueurs sur le serveur de leur communauté : un intervalle continu de présence par ligne
-- (signes de vie du mod fusionnés quand ils sont espacés de moins de la coupure tolérée). Les sessions courtes restent
-- ici pour l'historique ; seules celles qui atteignent le seuil de la communauté (60 min par défaut) comptent dans la
-- disponibilité. Aucune donnée existante n'est modifiée ; le remplissage du passé se fait à la main avec
-- tools/rebuild-player-sessions.php (essai à blanc par défaut). Retour arrière : migrations/rollback/player_sessions_down.sql.

CREATE TABLE IF NOT EXISTS player_sessions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED DEFAULT NULL COMMENT 'compte Athena relié au Steam au moment de l''enregistrement (le rattachement est relu par Steam)',
  steam_uid VARCHAR(32) NOT NULL,
  started_at DATETIME NOT NULL,
  ended_at DATETIME DEFAULT NULL COMMENT 'vide tant que la session est en cours ou que son dernier signe de vie est récent',
  last_seen_at DATETIME NOT NULL,
  duration_s INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'last_seen_at - started_at, plafonné à 24 h',
  aberrant TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 : plus de 24 h continues, durée plafonnée',
  source VARCHAR(16) NOT NULL DEFAULT 'heartbeat' COMMENT 'heartbeat|backfill',
  server_id INT UNSIGNED DEFAULT NULL COMMENT 'serveur déclaré de la communauté (tenant_game_servers.id)',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_player_sessions_start (tenant_id, steam_uid, started_at),
  KEY idx_player_sessions_user (tenant_id, user_id, started_at),
  KEY idx_player_sessions_steam (steam_uid, started_at),
  KEY idx_player_sessions_seen (tenant_id, steam_uid, last_seen_at),
  CONSTRAINT fk_player_sessions_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
