-- Refonte module documentaire : extension documents, document_versions, nouvelles tables
-- Exécution incrémentale via run-migrations.php (colonnes/tables créées si absentes)

-- 1. Extension table documents
ALTER TABLE documents
  ADD COLUMN uuid CHAR(36) NULL UNIQUE AFTER id,
  ADD COLUMN short_description VARCHAR(500) NULL AFTER slug,
  ADD COLUMN document_type VARCHAR(100) NULL AFTER short_description,
  ADD COLUMN classification_level VARCHAR(50) NOT NULL DEFAULT 'interne' AFTER document_category_id,
  ADD COLUMN visibility_scope VARCHAR(50) NOT NULL DEFAULT 'private' AFTER classification_level,
  ADD COLUMN owner_user_id INT UNSIGNED NULL AFTER visibility_scope,
  ADD COLUMN author_user_id INT UNSIGNED NULL AFTER owner_user_id,
  ADD COLUMN parent_document_id INT UNSIGNED NULL AFTER author_user_id,
  ADD COLUMN relation_type VARCHAR(50) NULL AFTER parent_document_id,
  ADD COLUMN version_label VARCHAR(50) NULL AFTER relation_type,
  ADD COLUMN sort_order INT UNSIGNED NOT NULL DEFAULT 0 AFTER version_label,
  ADD COLUMN current_file_id INT UNSIGNED NULL AFTER sort_order,
  ADD COLUMN formation_id INT UNSIGNED NULL AFTER current_file_id,
  ADD COLUMN equipment_class_id INT UNSIGNED NULL AFTER formation_id,
  ADD COLUMN unit_id INT UNSIGNED NULL AFTER equipment_class_id,
  ADD COLUMN operator_id INT UNSIGNED NULL AFTER unit_id,
  ADD COLUMN mission_id VARCHAR(128) NULL AFTER operator_id,
  ADD COLUMN effective_at DATETIME NULL AFTER mission_id,
  ADD COLUMN review_due_at DATETIME NULL AFTER effective_at,
  ADD COLUMN expires_at DATETIME NULL AFTER review_due_at,
  ADD COLUMN download_allowed TINYINT(1) NOT NULL DEFAULT 1 AFTER expires_at,
  ADD COLUMN print_allowed TINYINT(1) NOT NULL DEFAULT 1 AFTER download_allowed,
  ADD COLUMN locked TINYINT(1) NOT NULL DEFAULT 0 AFTER print_allowed,
  ADD COLUMN tags JSON NULL AFTER locked,
  ADD COLUMN inherit_parent_security TINYINT(1) NOT NULL DEFAULT 0 AFTER tags,
  ADD INDEX idx_documents_status (status),
  ADD INDEX idx_documents_owner (owner_user_id),
  ADD INDEX idx_documents_parent (parent_document_id),
  ADD INDEX idx_documents_classification (classification_level),
  ADD INDEX idx_documents_visibility (visibility_scope);

-- Migrer created_by vers owner_user_id / author_user_id pour lignes existantes
UPDATE documents SET owner_user_id = created_by WHERE owner_user_id IS NULL AND created_by IS NOT NULL;
UPDATE documents SET author_user_id = created_by WHERE author_user_id IS NULL AND created_by IS NOT NULL;

-- Remplir uuid pour les lignes existantes
UPDATE documents SET uuid = LOWER(UUID()) WHERE uuid IS NULL;

ALTER TABLE documents ADD CONSTRAINT documents_owner_user_id_fk FOREIGN KEY (owner_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE documents ADD CONSTRAINT documents_author_user_id_fk FOREIGN KEY (author_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE documents ADD CONSTRAINT documents_parent_document_id_fk FOREIGN KEY (parent_document_id) REFERENCES documents (id) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE documents ADD CONSTRAINT documents_current_file_id_fk FOREIGN KEY (current_file_id) REFERENCES document_versions (id) ON DELETE SET NULL ON UPDATE CASCADE;

-- 2. Extension document_versions
ALTER TABLE document_versions
  ADD COLUMN original_name VARCHAR(255) NULL AFTER file_path,
  ADD COLUMN version_label VARCHAR(50) NULL AFTER change_notes;

-- 3. Nouvelles tables
CREATE TABLE IF NOT EXISTS document_collaborators (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  document_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  role VARCHAR(50) NOT NULL,
  granted_by INT UNSIGNED NULL,
  granted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_document_collaborator (document_id, user_id, role),
  KEY idx_document_collaborators_user (user_id),
  CONSTRAINT fk_document_collaborators_document FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE,
  CONSTRAINT fk_document_collaborators_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS document_permissions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  document_id INT UNSIGNED NOT NULL,
  permission_type VARCHAR(50) NOT NULL,
  permission_value VARCHAR(190) NOT NULL,
  access_level VARCHAR(50) NOT NULL DEFAULT 'read',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_document_permissions_lookup (permission_type, permission_value, access_level),
  CONSTRAINT fk_document_permissions_document FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS document_relations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  parent_document_id INT UNSIGNED NOT NULL,
  child_document_id INT UNSIGNED NOT NULL,
  relation_type VARCHAR(50) NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_document_relation (parent_document_id, child_document_id, relation_type),
  CONSTRAINT fk_document_relations_parent FOREIGN KEY (parent_document_id) REFERENCES documents (id) ON DELETE CASCADE,
  CONSTRAINT fk_document_relations_child FOREIGN KEY (child_document_id) REFERENCES documents (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS document_audit_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  document_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  action VARCHAR(100) NOT NULL,
  old_value JSON NULL,
  new_value JSON NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_document_audit_log_document (document_id),
  KEY idx_document_audit_log_user (user_id),
  CONSTRAINT fk_document_audit_log_document FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
