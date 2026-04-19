-- Portail candidat par jeton temporaire (suivi + échanges)
CREATE TABLE IF NOT EXISTS enlistment_candidate_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  enlistment_id BIGINT UNSIGNED NOT NULL,
  access_token CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  last_status_snapshot VARCHAR(32) NULL,
  last_interview_slot DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_enlistment_candidate_tokens_token (access_token),
  UNIQUE KEY uq_enlistment_candidate_tokens_enlistment (tenant_id, enlistment_id),
  KEY idx_enlistment_candidate_tokens_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS enlistment_candidate_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  enlistment_id BIGINT UNSIGNED NOT NULL,
  entry_kind ENUM('candidate','staff') NOT NULL DEFAULT 'candidate',
  body TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_enlistment_candidate_messages_scope (tenant_id, enlistment_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
