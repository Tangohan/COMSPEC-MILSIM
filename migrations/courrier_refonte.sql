-- Refonte Courrier : snippets, vérification publique, caviardage
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS courrier_snippets (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NULL,
  code VARCHAR(80) NOT NULL,
  label VARCHAR(255) NOT NULL,
  phase VARCHAR(20) NOT NULL,
  body TEXT NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY tenant_id (tenant_id),
  KEY phase (phase),
  UNIQUE KEY uq_snippet_tenant_code (tenant_id, code),
  CONSTRAINT courrier_snippets_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
