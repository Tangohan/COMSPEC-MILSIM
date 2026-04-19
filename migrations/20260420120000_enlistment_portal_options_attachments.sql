-- Options portail candidat par dossier + pièces jointes (appliqué aussi via bootstrap/enlistment_portal_attachments_migration.php)
ALTER TABLE enlistments ADD COLUMN candidate_portal_allow_files TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE enlistments ADD COLUMN candidate_portal_allow_audio TINYINT(1) NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS enlistment_candidate_attachments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  enlistment_id BIGINT UNSIGNED NOT NULL,
  kind ENUM('file','audio') NOT NULL DEFAULT 'file',
  original_name VARCHAR(255) NOT NULL DEFAULT '',
  mime VARCHAR(160) NOT NULL DEFAULT '',
  size_bytes INT UNSIGNED NOT NULL DEFAULT 0,
  storage_path VARCHAR(512) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_enlistment_candidate_attachments_scope (tenant_id, enlistment_id, created_at),
  KEY idx_enlistment_candidate_attachments_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
