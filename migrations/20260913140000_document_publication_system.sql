-- Système de publication interne Athena — types configurables + métadonnées de diffusion

CREATE TABLE IF NOT EXISTS document_types (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  code VARCHAR(32) NOT NULL,
  label VARCHAR(120) NOT NULL,
  description TEXT NULL,
  color VARCHAR(32) NULL,
  icon VARCHAR(64) NULL,
  code_prefix VARCHAR(16) NOT NULL,
  default_reading_required TINYINT(1) NOT NULL DEFAULT 0,
  default_acknowledgment_required TINYINT(1) NOT NULL DEFAULT 0,
  default_require_validation TINYINT(1) NOT NULL DEFAULT 0,
  numbering_pattern VARCHAR(64) NOT NULL DEFAULT '{PREFIX}-{YEAR}-{SEQ}',
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_doc_type_code (tenant_id, code),
  KEY idx_doc_type_active (tenant_id, is_active, sort_order),
  CONSTRAINT fk_doc_type_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS document_reminder_rules (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  code VARCHAR(32) NOT NULL,
  label VARCHAR(120) NOT NULL,
  trigger_event VARCHAR(32) NOT NULL,
  offset_hours INT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_doc_reminder_rule (tenant_id, code),
  CONSTRAINT fk_doc_reminder_rule_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
