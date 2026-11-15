-- SSE : étiquettes des fiches de renseignement. UN SEUL catalogue par communauté pour tout le bureau : la table
-- sse_attach_tags de la fenêtre « Rattacher » (étape sse_attachments), que database/steps/4850_sse_tags.php complète de
-- colonnes ajoutées si elles manquent (portée, famille, code, libellés, description, complément, ordre, origine, état...).
-- Ce fichier ne crée que les deux tables NOUVELLES ; aucune donnée modifiée, les fiches gardent leurs catégories.
--
--  sse_tag_links : liaison étiquette ↔ objet (une fiche : object_type « note »), par la clé (slug) du catalogue, avec un
--    complément libre (numéro du besoin, liste REL TO, échéance). Une entrée du catalogue livré se pose sans ligne en base.
--  sse_field_note_settings : réglages des fiches (types, trames, règles), une ligne par clé et par communauté.
--
-- Sans cette étape, le catalogue livré reste en lecture seule et les fiches ne reçoivent pas de nouvelles étiquettes.
-- Retour arrière : migrations/rollback/sse_tags_down.sql.

CREATE TABLE IF NOT EXISTS sse_tag_links (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  scope VARCHAR(24) NOT NULL DEFAULT 'field_note',
  object_type VARCHAR(24) NOT NULL COMMENT 'note (fiche de renseignement)...',
  object_id VARCHAR(40) NOT NULL,
  tag_slug VARCHAR(40) NOT NULL COMMENT 'sse_attach_tags.slug ou clé du catalogue livré',
  qualifier VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'complément libre : numéro du besoin, liste REL TO, échéance',
  created_by_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sse_tag_link (tenant_id, scope, object_type, object_id, tag_slug),
  KEY idx_sse_tag_link_tag (tenant_id, scope, tag_slug),
  CONSTRAINT fk_sse_tag_link_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sse_field_note_settings (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  setting_key VARCHAR(64) NOT NULL COMMENT 'kind.FRM, kind.FRT... (type de fiche) ou rules',
  value_json TEXT NULL,
  updated_by_user_id INT UNSIGNED NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sse_field_note_setting (tenant_id, setting_key),
  CONSTRAINT fk_sse_field_note_setting_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
