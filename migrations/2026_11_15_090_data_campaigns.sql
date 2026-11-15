-- Campagne de fiabilisation des données : chaque année (et à la demande), la communauté ouvre une campagne ; chaque
-- membre relit sa propre fiche bloc par bloc, confirme qu'elle est exacte ou signale ce qui ne l'est pas ; l'encadrement
-- suit l'avancement et traite les signalements. Rien n'est modifié automatiquement dans les fiches : une correction
-- passe par les demandes de correction existantes (personnel_correction_requests) ou par le traitement d'un signalement.
-- Aucune donnée d'identité d'emprunt, de couverture ou de papiers (SSE) n'est enregistrée ici.
-- Ouverture automatique chaque année : active par défaut pour toute communauté (désactivable), une seule campagne ouverte par
-- Athena par communauté et par année (clé uq_data_campaign_auto).
-- Retour arrière : migrations/rollback/data_campaigns_down.sql.

CREATE TABLE IF NOT EXISTS data_campaign_settings (
  tenant_id INT UNSIGNED NOT NULL,
  auto_enabled TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'ouverture automatique chaque année (activée par défaut ; sans ligne, elle l''est aussi)',
  emails_enabled TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'e-mails d''ouverture et de rappel (activés par défaut)',
  open_md VARCHAR(5) NOT NULL DEFAULT '12-01' COMMENT 'jour et mois d''ouverture (MM-JJ)',
  close_md VARCHAR(5) NOT NULL DEFAULT '12-31' COMMENT 'jour et mois de la date limite (MM-JJ)',
  blocks_json TEXT DEFAULT NULL COMMENT 'blocs inclus (liste de clés), tous si vide',
  unit_ids_json TEXT DEFAULT NULL COMMENT 'unités concernées (liste d''identifiants), toute la communauté si vide',
  reminder_days_json VARCHAR(100) DEFAULT NULL COMMENT 'jours avant la date limite, ex. [14,3,0]',
  updated_by INT UNSIGNED DEFAULT NULL,
  updated_at DATETIME DEFAULT NULL,
  PRIMARY KEY (tenant_id),
  CONSTRAINT fk_data_campaign_settings_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS data_campaigns (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  campaign_year SMALLINT UNSIGNED NOT NULL,
  title VARCHAR(120) NOT NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'draft' COMMENT 'draft|open|closed',
  blocks_json TEXT DEFAULT NULL,
  unit_ids_json TEXT DEFAULT NULL COMMENT 'périmètre : unités (et leurs sous-unités), toute la communauté si vide',
  reminder_days_json VARCHAR(100) DEFAULT NULL,
  opens_on DATE DEFAULT NULL,
  due_on DATE DEFAULT NULL,
  auto_created TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'ouverte par Athena (tâche planifiée, commande ou bouton « Ouvrir maintenant »)',
  auto_year SMALLINT UNSIGNED DEFAULT NULL COMMENT 'année de la campagne ouverte par Athena : une seule par communauté et par année',
  forced_on DATE DEFAULT NULL COMMENT 'jour où l''ouverture a été forcée avant la période automatique',
  created_by INT UNSIGNED DEFAULT NULL,
  opened_at DATETIME DEFAULT NULL,
  closed_at DATETIME DEFAULT NULL,
  closed_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_data_campaign_auto (tenant_id, auto_year),
  KEY idx_data_campaigns_tenant (tenant_id, status, campaign_year),
  CONSTRAINT fk_data_campaigns_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS data_campaign_members (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  campaign_id INT UNSIGNED NOT NULL,
  tenant_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  unit_id INT UNSIGNED DEFAULT NULL COMMENT 'unité principale à l''ouverture, pour l''avancement par unité',
  blocks_json TEXT DEFAULT NULL COMMENT 'réponse par bloc : {bloc: {state: ok|flag, at, hash}}',
  snapshot_json MEDIUMTEXT DEFAULT NULL COMMENT 'ce que le membre a vu à la confirmation : {bloc: {hash, rows}}',
  snapshot_hash CHAR(64) DEFAULT NULL,
  confirmed_at DATETIME DEFAULT NULL,
  first_seen_at DATETIME DEFAULT NULL,
  updated_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_data_campaign_member (campaign_id, user_id),
  KEY idx_data_campaign_members_user (tenant_id, user_id, confirmed_at),
  CONSTRAINT fk_data_campaign_members_campaign FOREIGN KEY (campaign_id) REFERENCES data_campaigns (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS data_campaign_flags (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  campaign_id INT UNSIGNED NOT NULL,
  tenant_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  block_key VARCHAR(20) NOT NULL,
  message VARCHAR(1000) NOT NULL,
  correction_request_id INT UNSIGNED DEFAULT NULL COMMENT 'demande de correction de fiche ouverte pour ce signalement',
  status VARCHAR(10) NOT NULL DEFAULT 'open' COMMENT 'open|handled|withdrawn',
  handled_by INT UNSIGNED DEFAULT NULL,
  handled_at DATETIME DEFAULT NULL,
  answer VARCHAR(1000) DEFAULT NULL COMMENT 'réponse de l''encadrement, lue par le membre',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_data_campaign_flags_campaign (campaign_id, status),
  KEY idx_data_campaign_flags_user (tenant_id, user_id, campaign_id),
  CONSTRAINT fk_data_campaign_flags_campaign FOREIGN KEY (campaign_id) REFERENCES data_campaigns (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS data_campaign_reminders (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  campaign_id INT UNSIGNED NOT NULL,
  tenant_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 : repère « rappel terminé pour tous »',
  slot VARCHAR(24) NOT NULL COMMENT 'j14|j3|j0 (automatique) ou manual-AAAAMMJJ (relance de la gestion)',
  sent_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_data_campaign_reminder (campaign_id, user_id, slot),
  KEY idx_data_campaign_reminders_user (tenant_id, user_id, created_at),
  CONSTRAINT fk_data_campaign_reminders_campaign FOREIGN KEY (campaign_id) REFERENCES data_campaigns (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
