-- SSE éditeur de documents : brouillon automatique côté serveur (un brouillon par rédacteur et par document ; document_id = 0
-- pour un document pas encore créé). Le corps est le HTML déjà assaini par App\Support\SseRichText. Aucune donnée modifiée.
-- Retour arrière : migrations/rollback/sse_document_drafts_down.sql.

CREATE TABLE IF NOT EXISTS sse_document_drafts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL DEFAULT 0,
  guest_key VARCHAR(64) NOT NULL DEFAULT '',
  document_id INT UNSIGNED NOT NULL DEFAULT 0,
  title VARCHAR(240) NOT NULL DEFAULT '',
  body MEDIUMTEXT NOT NULL,
  document_type VARCHAR(32) NOT NULL DEFAULT 'note_analyse',
  classification VARCHAR(32) NOT NULL DEFAULT 'confidentiel',
  case_id INT UNSIGNED NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sse_doc_draft_owner (tenant_id, user_id, guest_key, document_id),
  KEY idx_sse_doc_draft_doc (tenant_id, document_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
