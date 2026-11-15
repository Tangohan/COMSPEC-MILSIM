-- SSE identités : avis de recherche (fait de jeu de rôle, aucune donnée réelle), chronologie unique de chaque identité
-- fictive (append-only : jamais modifiée ni effacée), réglages de la notice par communauté et détections système (une
-- identité sous avis actif qui réapparaît dans le jeu ou sur le site). Un seul avis actif par identité : active_key vaut 1 tant que l'avis est actif, NULL ensuite
-- (plusieurs NULL sont permis par la clé unique). Aucune donnée n'est ajoutée ni modifiée.
-- Retour arrière : migrations/rollback/sse_wanted_notices_down.sql.

CREATE TABLE IF NOT EXISTS sse_wanted_notices (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  identity_id INT UNSIGNED NOT NULL,
  ref VARCHAR(40) NOT NULL COMMENT 'référence générée (format réglable par communauté), ex. A-0123/2026',
  ref_year SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'année du compteur, 0 si le format n''en porte pas',
  ref_seq INT UNSIGNED NOT NULL DEFAULT 0,
  notice_type VARCHAR(32) NOT NULL DEFAULT 'poi',
  threat_level VARCHAR(10) NOT NULL DEFAULT 'medium' COMMENT 'low|medium|high|critical',
  armed VARCHAR(8) NOT NULL DEFAULT 'unknown' COMMENT 'yes|no|unknown',
  reason TEXT NULL COMMENT 'motifs, charges ou raison de l''intérêt',
  instructions TEXT NULL COMMENT 'mesures à prendre',
  zone VARCHAR(120) NULL COMMENT 'pays ou zone',
  last_seen_place VARCHAR(160) NULL,
  last_seen_at DATETIME NULL,
  contact_label VARCHAR(160) NULL COMMENT 'service demandeur (fictif)',
  contact_detail VARCHAR(200) NULL,
  authority VARCHAR(160) NULL COMMENT 'autorité émettrice fictive au moment de l''émission',
  status VARCHAR(10) NOT NULL DEFAULT 'active' COMMENT 'active|lifted|expired|captured',
  active_key TINYINT UNSIGNED NULL COMMENT '1 tant que l''avis est actif, NULL ensuite',
  issued_at DATETIME NOT NULL,
  expires_at DATETIME NULL,
  closed_at DATETIME NULL,
  closed_by_user_id INT UNSIGNED NULL,
  closed_reason VARCHAR(500) NULL,
  created_by_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sse_wanted_ref (tenant_id, ref),
  UNIQUE KEY uq_sse_wanted_active (tenant_id, identity_id, active_key),
  KEY idx_sse_wanted_identity (tenant_id, identity_id, id),
  KEY idx_sse_wanted_status (tenant_id, status, issued_at),
  KEY idx_sse_wanted_seq (tenant_id, ref_year, ref_seq),
  CONSTRAINT fk_sse_wanted_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sse_identity_events (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  identity_id INT UNSIGNED NOT NULL,
  event_type VARCHAR(40) NOT NULL,
  section VARCHAR(12) NOT NULL DEFAULT 'base' COMMENT 'base|truth : droit de lecture exigé pour voir la ligne',
  summary VARCHAR(255) NOT NULL,
  details_json TEXT NULL COMMENT 'champs modifiés (ancien et nouveau) ou compléments',
  notice_id INT UNSIGNED NULL,
  actor_user_id INT UNSIGNED NULL,
  actor_label VARCHAR(80) NULL,
  source VARCHAR(12) NOT NULL DEFAULT 'web' COMMENT 'web|game|system',
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_sse_idevents_identity (tenant_id, identity_id, id),
  KEY idx_sse_idevents_type (tenant_id, event_type, id),
  CONSTRAINT fk_sse_idevents_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sse_detections (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  identity_id INT UNSIGNED NOT NULL,
  notice_id INT UNSIGNED NOT NULL,
  detection_type VARCHAR(24) NOT NULL COMMENT 'id_check|seek|file_query|photo|link|phone|cross_match',
  source VARCHAR(12) NOT NULL DEFAULT 'system' COMMENT 'game|web|system',
  source_ref VARCHAR(80) NULL COMMENT 'contrôle, fiche SEEK II ou requête à l''origine',
  place VARCHAR(160) NULL,
  occurred_at DATETIME NULL,
  operator_label VARCHAR(80) NULL,
  operator_user_id INT UNSIGNED NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'new' COMMENT 'new|acknowledged|dismissed',
  comment VARCHAR(500) NULL,
  handled_by_user_id INT UNSIGNED NULL,
  handled_at DATETIME NULL,
  repeat_count INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'répétitions regroupées dans le laps de temps de déduplication',
  last_repeat_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_sse_detections_status (tenant_id, status, id),
  KEY idx_sse_detections_dedupe (tenant_id, notice_id, detection_type, created_at),
  KEY idx_sse_detections_identity (tenant_id, identity_id, id),
  CONSTRAINT fk_sse_detections_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sse_wanted_settings (
  tenant_id INT UNSIGNED NOT NULL,
  settings_json TEXT NULL COMMENT 'autorité émettrice fictive, libellé, format de référence, types d''avis, pied de page',
  updated_by_user_id INT UNSIGNED NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (tenant_id),
  CONSTRAINT fk_sse_wanted_settings_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
