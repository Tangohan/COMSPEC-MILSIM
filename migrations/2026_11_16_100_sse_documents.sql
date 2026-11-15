-- SSE fiches PDF d'une personne ou d'une identité fictive (jeu de rôle, aucune donnée réelle) : documents émis (texte
-- réellement imprimé et instantané des données, conservés pour le re-téléchargement), brouillons de rédaction par fiche et
-- par modèle, trames de phrases réutilisables par communauté. Aucune donnée n'est ajoutée ni modifiée.
-- Retour arrière : migrations/rollback/sse_documents_down.sql.

CREATE TABLE IF NOT EXISTS sse_doc_issues (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  person_id INT UNSIGNED NULL,
  identity_id INT UNSIGNED NULL,
  model VARCHAR(24) NOT NULL COMMENT 'notice|identification|vigilance|profile|control',
  ref VARCHAR(40) NOT NULL COMMENT 'référence générée, ex. FV-2026-0007',
  ref_year SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  ref_seq INT UNSIGNED NOT NULL DEFAULT 0,
  lang VARCHAR(2) NOT NULL DEFAULT 'fr',
  classification VARCHAR(20) NOT NULL DEFAULT 'encadrement',
  faces VARCHAR(4) NOT NULL DEFAULT 'auto',
  texts_json MEDIUMTEXT NULL COMMENT 'texte réellement imprimé, champ par champ',
  snapshot_json MEDIUMTEXT NULL COMMENT 'données imprimées (sans image) pour le re-téléchargement',
  sensitive_json TEXT NULL COMMENT 'catégories sensibles contenues dans le document',
  issued_by_user_id INT UNSIGNED NULL,
  issued_by_label VARCHAR(80) NULL,
  issued_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sse_doc_issue_ref (tenant_id, ref),
  KEY idx_sse_doc_issue_person (tenant_id, person_id, id),
  KEY idx_sse_doc_issue_identity (tenant_id, identity_id, id),
  KEY idx_sse_doc_issue_seq (tenant_id, model, ref_year, ref_seq),
  CONSTRAINT fk_sse_doc_issue_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sse_doc_drafts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  person_id INT UNSIGNED NULL,
  identity_id INT UNSIGNED NULL,
  model VARCHAR(24) NOT NULL,
  lang VARCHAR(2) NOT NULL DEFAULT 'fr',
  title VARCHAR(80) NOT NULL DEFAULT '',
  fields_json MEDIUMTEXT NULL COMMENT 'textes et réglages du brouillon',
  created_by_user_id INT UNSIGNED NULL,
  updated_by_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_sse_doc_draft_person (tenant_id, person_id, model, id),
  KEY idx_sse_doc_draft_identity (tenant_id, identity_id, model, id),
  CONSTRAINT fk_sse_doc_draft_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sse_doc_phrases (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  model VARCHAR(24) NOT NULL DEFAULT '' COMMENT 'vide : tous les modèles',
  lang VARCHAR(2) NOT NULL DEFAULT 'fr',
  label VARCHAR(80) NOT NULL,
  body TEXT NOT NULL,
  created_by_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_sse_doc_phrase_tenant (tenant_id, model, lang),
  CONSTRAINT fk_sse_doc_phrase_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
