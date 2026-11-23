-- SSE éditeur de documents : compteur de références par communauté, type et année, et signatures au pad (une ligne par signature, image dans le stockage privé). Aucune donnée modifiée.
-- Retour arrière : migrations/rollback/sse_document_pro_down.sql.

CREATE TABLE IF NOT EXISTS sse_document_counters (
  tenant_id INT UNSIGNED NOT NULL,
  prefix VARCHAR(12) NOT NULL,
  year SMALLINT UNSIGNED NOT NULL,
  last_seq INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (tenant_id, prefix, year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sse_document_signatures (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  document_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  signer_label VARCHAR(120) NOT NULL,
  image_path VARCHAR(190) NOT NULL,
  doc_hash CHAR(64) NOT NULL,
  signed_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_sse_doc_sig_doc (tenant_id, document_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
