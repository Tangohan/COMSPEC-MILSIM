-- SSE modèles de documents personnels : un modèle enregistré à partir d'un document, privé à son auteur ou partagé dans la communauté (tenant_id). Le corps est le HTML déjà assaini par App\Support\SseRichText. Aucune donnée modifiée.
-- Retour arrière : migrations/rollback/sse_document_templates_down.sql.

CREATE TABLE IF NOT EXISTS sse_document_templates (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL DEFAULT 0,
  guest_key VARCHAR(64) NOT NULL DEFAULT '',
  title VARCHAR(120) NOT NULL,
  description VARCHAR(300) NOT NULL DEFAULT '',
  document_type VARCHAR(32) NOT NULL DEFAULT 'note_analyse',
  classification VARCHAR(32) NOT NULL DEFAULT 'confidentiel',
  body MEDIUMTEXT NOT NULL,
  shared TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_sse_doc_tpl_tenant (tenant_id, shared, updated_at),
  KEY idx_sse_doc_tpl_owner (tenant_id, user_id, guest_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
