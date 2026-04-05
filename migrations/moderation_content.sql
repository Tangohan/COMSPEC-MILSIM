-- Modération contenus / fichiers (artefacts polymorphes + décisions humaines)
-- Idempotent : exécuté via bootstrap/moderation_content_migration.php

CREATE TABLE IF NOT EXISTS moderation_artifacts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED DEFAULT NULL COMMENT 'Auteur upload ou saisie',
  source_type VARCHAR(40) NOT NULL COMMENT 'forum_upload|document_version|courrier_document',
  source_id INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'PK métier (version_id, courrier doc id); 0 si N/A',
  source_key VARCHAR(255) DEFAULT NULL COMMENT 'Clé secondaire (ex. nom fichier forum)',
  file_path VARCHAR(500) DEFAULT NULL COMMENT 'Chemin relatif app (storage/... ou public/...)',
  original_name VARCHAR(255) DEFAULT NULL,
  mime VARCHAR(120) DEFAULT NULL,
  sha256 CHAR(64) DEFAULT NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'pending_scan' COMMENT 'pending_scan|clean|quarantined|rejected|approved_override',
  risk_score SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  reason_codes JSON DEFAULT NULL,
  scan_log JSON DEFAULT NULL,
  ruleset_version VARCHAR(32) DEFAULT NULL,
  expires_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_mod_tenant_state_score (tenant_id, state, risk_score, created_at),
  KEY idx_mod_source (source_type, source_id),
  KEY idx_mod_source_key (tenant_id, source_type, source_key(120)),
  CONSTRAINT moderation_artifacts_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT moderation_artifacts_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS moderation_decisions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  artifact_id BIGINT UNSIGNED NOT NULL,
  actor_user_id INT UNSIGNED DEFAULT NULL,
  action VARCHAR(32) NOT NULL COMMENT 'approve_override|reject|release',
  reason_code VARCHAR(64) DEFAULT NULL,
  note TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_mod_dec_artifact (artifact_id),
  CONSTRAINT moderation_decisions_artifact_fk FOREIGN KEY (artifact_id) REFERENCES moderation_artifacts (id) ON DELETE CASCADE,
  CONSTRAINT moderation_decisions_actor_fk FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
