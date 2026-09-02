-- Référentiel doctrinal ATHENA — extension du module documents (idempotent via bootstrap)

-- Portée tenant / plateforme sur documents
ALTER TABLE documents ADD COLUMN scope VARCHAR(16) NOT NULL DEFAULT 'tenant' AFTER tenant_id;
ALTER TABLE documents ADD COLUMN short_title VARCHAR(120) NULL AFTER title;

-- Extensions versions pour workflow doctrinal
ALTER TABLE document_versions ADD COLUMN version_major SMALLINT UNSIGNED NULL AFTER version_number;
ALTER TABLE document_versions ADD COLUMN version_minor SMALLINT UNSIGNED NULL AFTER version_major;
ALTER TABLE document_versions ADD COLUMN acknowledgment_reset TINYINT(1) NOT NULL DEFAULT 0 AFTER change_notes;
ALTER TABLE document_versions ADD COLUMN change_summary TEXT NULL AFTER acknowledgment_reset;

CREATE TABLE IF NOT EXISTS document_reference_domains (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  code VARCHAR(32) NOT NULL,
  label VARCHAR(120) NOT NULL,
  doc_prefix VARCHAR(32) NOT NULL,
  color VARCHAR(32) NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_doc_ref_domain (tenant_id, code),
  KEY idx_doc_ref_domain_prefix (tenant_id, doc_prefix),
  CONSTRAINT fk_doc_ref_domain_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS document_reference_subdomains (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  domain_id INT UNSIGNED NOT NULL,
  code VARCHAR(32) NOT NULL,
  label VARCHAR(120) NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uk_doc_ref_subdomain (tenant_id, domain_id, code),
  CONSTRAINT fk_doc_ref_subdomain_domain FOREIGN KEY (domain_id) REFERENCES document_reference_domains (id) ON DELETE CASCADE,
  CONSTRAINT fk_doc_ref_subdomain_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS document_reference_sequences (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  service_prefix VARCHAR(32) NOT NULL,
  domain_code VARCHAR(32) NOT NULL,
  year SMALLINT UNSIGNED NOT NULL,
  last_number INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uk_doc_ref_seq (tenant_id, service_prefix, domain_code, year),
  CONSTRAINT fk_doc_ref_seq_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS document_diffusion_levels (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  code VARCHAR(32) NOT NULL,
  label VARCHAR(120) NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uk_doc_diffusion (tenant_id, code),
  CONSTRAINT fk_doc_diffusion_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS document_doctrines (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  document_id INT UNSIGNED NOT NULL,
  tenant_id INT UNSIGNED NULL,
  scope VARCHAR(16) NOT NULL DEFAULT 'tenant',
  reference_code VARCHAR(64) NOT NULL,
  service_prefix VARCHAR(32) NOT NULL,
  domain_id INT UNSIGNED NULL,
  subdomain_id INT UNSIGNED NULL,
  domain_code VARCHAR(32) NULL,
  seq_year SMALLINT UNSIGNED NULL,
  seq_number INT UNSIGNED NULL,
  short_title VARCHAR(120) NULL,
  summary TEXT NULL,
  doctrine_status VARCHAR(32) NOT NULL DEFAULT 'draft',
  requirement_level VARCHAR(32) NOT NULL DEFAULT 'informative',
  diffusion_level_id INT UNSIGNED NULL,
  issuing_authority_type VARCHAR(32) NOT NULL DEFAULT 'tenant',
  issuing_unit_id INT UNSIGNED NULL,
  issuing_job_role_id INT UNSIGNED NULL,
  issuing_user_id INT UNSIGNED NULL,
  issuing_label VARCHAR(255) NULL,
  approver_user_id INT UNSIGNED NULL,
  effective_at DATETIME NULL,
  expires_at DATETIME NULL,
  acknowledgment_required TINYINT(1) NOT NULL DEFAULT 0,
  acknowledgment_deadline_at DATETIME NULL,
  reading_required TINYINT(1) NOT NULL DEFAULT 0,
  include_future_members TINYINT(1) NOT NULL DEFAULT 1,
  replaced_by_document_id INT UNSIGNED NULL,
  replaces_document_id INT UNSIGNED NULL,
  keywords_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  published_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_doc_doctrine_document (document_id),
  UNIQUE KEY uk_doc_doctrine_ref_tenant (tenant_id, reference_code),
  KEY idx_doc_doctrine_scope (scope),
  KEY idx_doc_doctrine_status (doctrine_status),
  CONSTRAINT fk_doc_doctrine_document FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE,
  CONSTRAINT fk_doc_doctrine_domain FOREIGN KEY (domain_id) REFERENCES document_reference_domains (id) ON DELETE SET NULL,
  CONSTRAINT fk_doc_doctrine_subdomain FOREIGN KEY (subdomain_id) REFERENCES document_reference_subdomains (id) ON DELETE SET NULL,
  CONSTRAINT fk_doc_doctrine_diffusion FOREIGN KEY (diffusion_level_id) REFERENCES document_diffusion_levels (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS document_audiences (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  document_id INT UNSIGNED NOT NULL,
  tenant_id INT UNSIGNED NOT NULL,
  audience_type VARCHAR(32) NOT NULL,
  audience_value VARCHAR(190) NOT NULL,
  include_children TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_doc_audience_lookup (document_id, audience_type),
  KEY idx_doc_audience_tenant (tenant_id, audience_type, audience_value),
  CONSTRAINT fk_doc_audience_document FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE,
  CONSTRAINT fk_doc_audience_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS document_acknowledgments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  document_id INT UNSIGNED NOT NULL,
  version_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  signed_at DATETIME NOT NULL,
  snapshot_first_name VARCHAR(120) NULL,
  snapshot_last_name VARCHAR(120) NULL,
  snapshot_display_name VARCHAR(255) NULL,
  snapshot_rank VARCHAR(120) NULL,
  snapshot_unit VARCHAR(255) NULL,
  snapshot_reference VARCHAR(64) NULL,
  snapshot_version_label VARCHAR(32) NULL,
  integrity_hash VARCHAR(128) NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(512) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_doc_ack_user_version (tenant_id, user_id, version_id),
  KEY idx_doc_ack_document (document_id),
  KEY idx_doc_ack_tenant_user (tenant_id, user_id),
  CONSTRAINT fk_doc_ack_document FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE,
  CONSTRAINT fk_doc_ack_version FOREIGN KEY (version_id) REFERENCES document_versions (id) ON DELETE CASCADE,
  CONSTRAINT fk_doc_ack_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_doc_ack_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS document_views (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  document_id INT UNSIGNED NOT NULL,
  version_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  first_viewed_at DATETIME NOT NULL,
  last_viewed_at DATETIME NOT NULL,
  view_count INT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uk_doc_view_user_version (tenant_id, user_id, version_id),
  KEY idx_doc_view_document (document_id),
  CONSTRAINT fk_doc_view_document FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE,
  CONSTRAINT fk_doc_view_version FOREIGN KEY (version_id) REFERENCES document_versions (id) ON DELETE CASCADE,
  CONSTRAINT fk_doc_view_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_doc_view_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS document_doctrine_reminders (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  document_id INT UNSIGNED NOT NULL,
  sent_by_user_id INT UNSIGNED NOT NULL,
  target_scope VARCHAR(32) NOT NULL,
  recipient_count INT UNSIGNED NOT NULL DEFAULT 0,
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_doc_reminder_document (document_id),
  CONSTRAINT fk_doc_reminder_document FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE,
  CONSTRAINT fk_doc_reminder_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
