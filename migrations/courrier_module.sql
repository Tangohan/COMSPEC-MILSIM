-- Module Bureau Courrier / Correspondance Officielle
-- Tables dédiées (sans conflit avec documents existants)
-- Exécution incrémentale via run-migrations.php

SET NAMES utf8mb4;

-- document_presets : formats de mise en page (A4 portrait, note interne, compte rendu, etc.)
CREATE TABLE IF NOT EXISTS document_presets (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NULL,
  name VARCHAR(255) NOT NULL,
  code VARCHAR(50) NOT NULL,
  paper_size VARCHAR(50) DEFAULT 'a4',
  orientation VARCHAR(20) DEFAULT 'portrait',
  margins_json JSON DEFAULT NULL,
  typography_json JSON DEFAULT NULL,
  header_config_json JSON DEFAULT NULL,
  footer_config_json JSON DEFAULT NULL,
  signature_config_json JSON DEFAULT NULL,
  layout_config_json JSON DEFAULT NULL,
  is_system TINYINT(1) NOT NULL DEFAULT 0,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY tenant_id (tenant_id),
  KEY code (code),
  CONSTRAINT document_presets_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- document_templates : modèles de documents (système, métier, personnels)
CREATE TABLE IF NOT EXISTS document_templates (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NULL,
  name VARCHAR(255) NOT NULL,
  slug VARCHAR(100) NOT NULL,
  category VARCHAR(100) DEFAULT NULL,
  description TEXT DEFAULT NULL,
  is_system TINYINT(1) NOT NULL DEFAULT 0,
  is_locked TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  preset_id INT UNSIGNED DEFAULT NULL,
  structure_json JSON DEFAULT NULL,
  body_template LONGTEXT DEFAULT NULL,
  created_by INT UNSIGNED DEFAULT NULL,
  updated_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY tenant_id (tenant_id),
  KEY slug (slug),
  KEY is_active (is_active),
  KEY preset_id (preset_id),
  CONSTRAINT document_templates_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT document_templates_preset_fk FOREIGN KEY (preset_id) REFERENCES document_presets (id) ON DELETE SET NULL,
  CONSTRAINT document_templates_created_by_fk FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT document_templates_updated_by_fk FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- courrier_documents : instances de courrier générées (brouillons, validés, envoyés)
CREATE TABLE IF NOT EXISTS courrier_documents (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,
  tenant_id INT UNSIGNED NOT NULL,
  template_id INT UNSIGNED DEFAULT NULL,
  preset_id INT UNSIGNED DEFAULT NULL,
  type VARCHAR(50) DEFAULT NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'draft',
  title VARCHAR(255) DEFAULT NULL,
  reference_number VARCHAR(100) DEFAULT NULL,
  subject VARCHAR(500) DEFAULT NULL,
  destination_label VARCHAR(500) DEFAULT NULL,
  issuer_label VARCHAR(500) DEFAULT NULL,
  body_rendered LONGTEXT DEFAULT NULL,
  variables_json JSON DEFAULT NULL,
  metadata_json JSON DEFAULT NULL,
  attachments_json JSON DEFAULT NULL,
  classification_level VARCHAR(50) DEFAULT 'interne',
  created_by INT UNSIGNED DEFAULT NULL,
  validated_by INT UNSIGNED DEFAULT NULL,
  signed_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  sent_at DATETIME DEFAULT NULL,
  archived_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uuid (uuid),
  KEY tenant_id (tenant_id),
  KEY status (status),
  KEY template_id (template_id),
  KEY created_by (created_by),
  KEY reference_number (reference_number),
  CONSTRAINT courrier_documents_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT courrier_documents_template_fk FOREIGN KEY (template_id) REFERENCES document_templates (id) ON DELETE SET NULL,
  CONSTRAINT courrier_documents_preset_fk FOREIGN KEY (preset_id) REFERENCES document_presets (id) ON DELETE SET NULL,
  CONSTRAINT courrier_documents_created_by_fk FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT courrier_documents_validated_by_fk FOREIGN KEY (validated_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT courrier_documents_signed_by_fk FOREIGN KEY (signed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- courrier_document_versions : snapshots de contenu pour historique
CREATE TABLE IF NOT EXISTS courrier_document_versions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  document_id INT UNSIGNED NOT NULL,
  version_number INT UNSIGNED NOT NULL DEFAULT 1,
  snapshot_json JSON NOT NULL,
  created_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY document_id (document_id),
  CONSTRAINT courrier_document_versions_document_fk FOREIGN KEY (document_id) REFERENCES courrier_documents (id) ON DELETE CASCADE,
  CONSTRAINT courrier_document_versions_created_by_fk FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- document_template_versions : versions des modèles
CREATE TABLE IF NOT EXISTS document_template_versions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  template_id INT UNSIGNED NOT NULL,
  version_number INT UNSIGNED NOT NULL DEFAULT 1,
  structure_json JSON DEFAULT NULL,
  body_template LONGTEXT DEFAULT NULL,
  created_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY template_id (template_id),
  CONSTRAINT document_template_versions_template_fk FOREIGN KEY (template_id) REFERENCES document_templates (id) ON DELETE CASCADE,
  CONSTRAINT document_template_versions_created_by_fk FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- document_variables_catalog : catalogue des variables disponibles (user, unit, document, etc.)
CREATE TABLE IF NOT EXISTS document_variables_catalog (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NULL,
  code VARCHAR(100) NOT NULL,
  label VARCHAR(255) NOT NULL,
  source_type VARCHAR(50) DEFAULT NULL,
  source_path VARCHAR(255) DEFAULT NULL,
  description VARCHAR(500) DEFAULT NULL,
  category VARCHAR(50) DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY tenant_id (tenant_id),
  KEY code (code),
  KEY category (category),
  CONSTRAINT document_variables_catalog_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- document_workflows : historique des changements de statut
CREATE TABLE IF NOT EXISTS document_workflows (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  document_id INT UNSIGNED NOT NULL,
  status_from VARCHAR(50) DEFAULT NULL,
  status_to VARCHAR(50) NOT NULL,
  action_label VARCHAR(255) DEFAULT NULL,
  comment TEXT DEFAULT NULL,
  acted_by INT UNSIGNED DEFAULT NULL,
  acted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY document_id (document_id),
  CONSTRAINT document_workflows_document_fk FOREIGN KEY (document_id) REFERENCES courrier_documents (id) ON DELETE CASCADE,
  CONSTRAINT document_workflows_acted_by_fk FOREIGN KEY (acted_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
