-- Sécurisation documentaire : code d'accès, signature numérique et timeline des accès.

ALTER TABLE documents
  ADD COLUMN require_access_code TINYINT(1) NOT NULL DEFAULT 0 AFTER inherit_parent_security,
  ADD COLUMN access_code_hash VARCHAR(255) NULL AFTER require_access_code,
  ADD COLUMN require_account_signature TINYINT(1) NOT NULL DEFAULT 0 AFTER access_code_hash,
  ADD COLUMN signature_mandatory_before_download TINYINT(1) NOT NULL DEFAULT 1 AFTER require_account_signature;

CREATE TABLE IF NOT EXISTS document_access_sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  document_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  session_token CHAR(64) NOT NULL,
  opened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  closed_at DATETIME NULL,
  read_seconds INT UNSIGNED NOT NULL DEFAULT 0,
  download_count INT UNSIGNED NOT NULL DEFAULT 0,
  signature_required TINYINT(1) NOT NULL DEFAULT 0,
  signature_completed_at DATETIME NULL,
  signature_name VARCHAR(190) NULL,
  signature_image_path VARCHAR(500) NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(500) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_document_access_session_token (session_token),
  KEY idx_document_access_sessions_lookup (tenant_id, document_id, user_id),
  CONSTRAINT fk_document_access_sessions_document FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE,
  CONSTRAINT fk_document_access_sessions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS document_access_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  session_id BIGINT UNSIGNED NOT NULL,
  document_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  event_type VARCHAR(80) NOT NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_document_access_events_document (document_id, created_at),
  KEY idx_document_access_events_session (session_id, created_at),
  CONSTRAINT fk_document_access_events_session FOREIGN KEY (session_id) REFERENCES document_access_sessions (id) ON DELETE CASCADE,
  CONSTRAINT fk_document_access_events_document FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
