-- SSE alertes (avis de recherche, notices, fiches de vigilance) : adresse de signalement à jeton opaque, service à informer
-- et levée d'une fiche de vigilance (sse_alert_links), signalements reçus du site ou du jeu (sse_alert_reports), propositions
-- à valider par le bureau (sse_alert_proposals), accusés de lecture (sse_alert_acks) et emblème téléversé d'un service
-- (sse_service_emblems). Aucune colonne ajoutée aux tables existantes, aucune donnée modifiée.
-- Retour arrière : migrations/rollback/sse_alerts_down.sql.

CREATE TABLE IF NOT EXISTS sse_alert_links (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  doc VARCHAR(12) NOT NULL COMMENT 'avis|vigilance',
  target_id INT UNSIGNED NOT NULL COMMENT 'sse_wanted_notices.id ou sse_doc_issues.id',
  token VARCHAR(32) NOT NULL COMMENT 'jeton opaque de la page de signalement',
  report_json TEXT NULL COMMENT 'service à informer : instantané (code, noms, sigle, autorité, canal, délai)',
  valid_until DATE NULL COMMENT 'fiche de vigilance : échéance',
  lifted_at DATETIME NULL,
  lifted_by_user_id INT UNSIGNED NULL,
  lifted_by_label VARCHAR(80) NULL,
  lift_reason VARCHAR(500) NULL,
  renewed_by_ref VARCHAR(40) NULL COMMENT 'référence du document qui remplace celui-ci',
  revoked_at DATETIME NULL COMMENT 'jeton révoqué par un gestionnaire',
  client_id VARCHAR(64) NULL COMMENT 'identifiant client du jeu (idempotence)',
  created_by_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sse_alert_link_token (token),
  UNIQUE KEY uq_sse_alert_link_target (tenant_id, doc, target_id),
  UNIQUE KEY uq_sse_alert_link_client (tenant_id, client_id),
  CONSTRAINT fk_sse_alert_link_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sse_alert_reports (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  link_id INT UNSIGNED NOT NULL,
  ref VARCHAR(24) NOT NULL COMMENT 'numéro de signalement, ex. SIG-2026-0042',
  ref_year SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  ref_seq INT UNSIGNED NOT NULL DEFAULT 0,
  what VARCHAR(8) NOT NULL DEFAULT 'seen' COMMENT 'seen|info',
  seen_at DATETIME NOT NULL,
  place VARCHAR(160) NULL,
  grid VARCHAR(24) NULL,
  pos_x DOUBLE NULL,
  pos_y DOUBLE NULL,
  body TEXT NOT NULL,
  direction VARCHAR(120) NULL,
  vehicle VARCHAR(160) NULL,
  certainty VARCHAR(8) NOT NULL DEFAULT 'medium' COMMENT 'low|medium|high',
  photo_path VARCHAR(255) NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'new' COMMENT 'new|acknowledged|corroborated|dismissed',
  status_note VARCHAR(500) NULL,
  handled_by_user_id INT UNSIGNED NULL,
  handled_at DATETIME NULL,
  source VARCHAR(8) NOT NULL DEFAULT 'web' COMMENT 'web|game',
  client_id VARCHAR(64) NULL,
  reporter_user_id INT UNSIGNED NULL,
  reporter_label VARCHAR(80) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sse_alert_report_ref (tenant_id, ref),
  UNIQUE KEY uq_sse_alert_report_client (tenant_id, client_id),
  KEY idx_sse_alert_report_link (tenant_id, link_id, id),
  KEY idx_sse_alert_report_status (tenant_id, status, id),
  CONSTRAINT fk_sse_alert_report_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sse_alert_proposals (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  doc VARCHAR(12) NOT NULL COMMENT 'avis|vigilance',
  payload_json MEDIUMTEXT NOT NULL COMMENT 'saisie proposée (sujet, type, niveau, codes, zone, validité, texte, service)',
  state VARCHAR(12) NOT NULL DEFAULT 'pending' COMMENT 'pending|accepted|rejected',
  result_ref VARCHAR(40) NULL COMMENT 'référence du document créé quand la proposition est acceptée',
  decision_note VARCHAR(500) NULL,
  decided_by_user_id INT UNSIGNED NULL,
  decided_at DATETIME NULL,
  source VARCHAR(8) NOT NULL DEFAULT 'game',
  client_id VARCHAR(64) NULL,
  created_by_user_id INT UNSIGNED NULL,
  created_by_label VARCHAR(80) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sse_alert_proposal_client (tenant_id, client_id),
  KEY idx_sse_alert_proposal_state (tenant_id, state, id),
  CONSTRAINT fk_sse_alert_proposal_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sse_alert_acks (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  link_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  acked_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sse_alert_ack (tenant_id, link_id, user_id),
  CONSTRAINT fk_sse_alert_ack_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sse_service_emblems (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  service_code VARCHAR(30) NOT NULL,
  path VARCHAR(255) NOT NULL COMMENT 'chemin privé (SseImageStorage)',
  thumb_path VARCHAR(255) NULL,
  mime VARCHAR(40) NOT NULL DEFAULT 'image/png',
  width SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  height SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  bytes INT UNSIGNED NOT NULL DEFAULT 0,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  confirmed_by_user_id INT UNSIGNED NULL COMMENT 'a confirmé : emblème de la communauté ou création originale',
  confirmed_by_label VARCHAR(80) NULL,
  confirmed_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sse_service_emblem (tenant_id, service_code),
  CONSTRAINT fk_sse_service_emblem_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
