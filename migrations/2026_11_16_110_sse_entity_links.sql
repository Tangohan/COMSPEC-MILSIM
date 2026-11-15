-- SSE : liens de provenance entre éléments du portail (personne, identité du registre, papier, contrôle, fiche SEEK II,
-- dossier, fiche de renseignement, avis de recherche, document émis, photo...). Une ligne dit POURQUOI deux éléments sont
-- liés : la relation, le motif en clair, le code de la règle quand un traitement automatique (Cerbère) l'a posé, l'auteur
-- (compte ou système) et la date. Les liens manuels posés depuis le widget « Liaisons » y sont aussi, avec leur motif.
-- Un seul lien par couple d'éléments et par communauté. Aucune donnée n'est ajoutée ni modifiée : sans cette table, le
-- widget déduit les liens des colonnes existantes (mode dégradé). Rétro-remplissage : tools/sse-backfill-links.php.
-- Retour arrière : migrations/rollback/sse_entity_links_down.sql.

CREATE TABLE IF NOT EXISTS sse_entity_links (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  source_type VARCHAR(24) NOT NULL COMMENT 'person|identity|paper|check|seek|notice|doc_issue|photo|case|interest|document|note|site|transmission|campaign...',
  source_id VARCHAR(40) NOT NULL,
  target_type VARCHAR(24) NOT NULL,
  target_id VARCHAR(40) NOT NULL,
  relation VARCHAR(32) NOT NULL DEFAULT 'related' COMMENT 'created_from|attached_to|presented|identified_as|filed_in|cited_in|related...',
  reason VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'motif en clair du lien',
  rule_code VARCHAR(64) NULL COMMENT 'règle du traitement automatique (cerbere.link.check_biokey...), NULL pour un lien manuel',
  created_by VARCHAR(8) NOT NULL DEFAULT 'system' COMMENT 'user|system',
  created_by_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sse_entity_link (tenant_id, source_type, source_id, target_type, target_id),
  KEY idx_sse_entity_link_src (tenant_id, source_type, source_id),
  KEY idx_sse_entity_link_dst (tenant_id, target_type, target_id),
  CONSTRAINT fk_sse_entity_link_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
