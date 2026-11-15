-- SSE : système d'étiquettes PARTAGÉ du bureau (fiches de renseignement, rattachements) et réglages des fiches par communauté.
-- Trois tables NOUVELLES, aucune colonne ajoutée ni donnée modifiée : les fiches gardent leurs catégories et leur type.
--
--  sse_tags : étiquettes d'une communauté, rangées par PORTÉE (scope : « field_note » pour les fiches, « attach » pour les
--    rattachements) et par famille. Une étiquette du catalogue livré n'a de ligne que si la communauté l'a réglée (libellé,
--    couleur, ordre, état) ; une étiquette propre a toujours la sienne. Unicité : communauté + portée + clé (slug).
--  sse_tag_links : liaison étiquette ↔ objet (une fiche : object_type « note »), par la clé de l'étiquette, avec un complément
--    libre (numéro du besoin, liste REL TO, échéance).
--  sse_field_note_settings : réglages des fiches (types, trames, règles), une ligne par clé et par communauté.
--
-- Sans ces tables, le catalogue livré s'affiche en lecture seule et l'étiquetage est désactivé avec un message.
-- Retour arrière : migrations/rollback/sse_tags_down.sql.

CREATE TABLE IF NOT EXISTS sse_tags (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  scope VARCHAR(24) NOT NULL DEFAULT 'field_note' COMMENT 'field_note|attach : qui utilise cette étiquette',
  family VARCHAR(24) NOT NULL DEFAULT '' COMMENT 'discipline|requirement|product|theme|framework|dissemination|validity|category ; vide hors fiches',
  code VARCHAR(40) NOT NULL DEFAULT '' COMMENT 'code court écrit sur la fiche (HUMINT, PIR, NOFORN...)',
  slug VARCHAR(40) NOT NULL COMMENT 'clé stable : code sans accents ni majuscules',
  name VARCHAR(80) NOT NULL COMMENT 'libellé français',
  name_en VARCHAR(80) NOT NULL DEFAULT '' COMMENT 'libellé anglais',
  description VARCHAR(255) NOT NULL DEFAULT '',
  description_en VARCHAR(255) NOT NULL DEFAULT '',
  color VARCHAR(12) NOT NULL DEFAULT 'slate' COMMENT 'slate|green|blue|amber|red|violet',
  qualifier VARCHAR(12) NOT NULL DEFAULT '' COMMENT 'complément attendu : number|text|date ou vide',
  sort_order INT NOT NULL DEFAULT 0,
  origin VARCHAR(12) NOT NULL DEFAULT 'custom' COMMENT 'catalog (réglage du catalogue livré) | custom (étiquette propre)',
  status VARCHAR(12) NOT NULL DEFAULT 'active' COMMENT 'active|disabled|archived',
  merged_into VARCHAR(40) NULL COMMENT 'clé de la cible après une fusion',
  created_by_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_by_user_id INT UNSIGNED NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sse_tag (tenant_id, scope, slug),
  KEY idx_sse_tag_family (tenant_id, scope, family, status),
  CONSTRAINT fk_sse_tag_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sse_tag_links (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  scope VARCHAR(24) NOT NULL DEFAULT 'field_note',
  object_type VARCHAR(24) NOT NULL COMMENT 'note (fiche de renseignement)...',
  object_id VARCHAR(40) NOT NULL,
  tag_slug VARCHAR(40) NOT NULL COMMENT 'sse_tags.slug ou clé du catalogue livré',
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
