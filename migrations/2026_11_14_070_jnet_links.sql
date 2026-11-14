-- JNET : liaisons entre unités (appui, partenaire, rattachement temporaire, hors hiérarchie de l'ORBAT) et liens entre
-- contenus (échange, page, tâche, opération, cible, document, unité, membre). Une liaison ne donne jamais plus de droits
-- que le modèle existant : elle marque l'autre unité dans les listes, ouvre le partage d'une information (jamais d'un
-- ordre) et, si le chef de l'espace le coche, la lecture de son espace. Un lien n'est affiché qu'après contrôle des droits
-- du lecteur sur l'objet lié. Retour arrière : migrations/rollback/jnet_links_down.sql.

CREATE TABLE IF NOT EXISTS jnet_unit_links (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  unit_id INT UNSIGNED NOT NULL COMMENT 'espace qui a créé la liaison (décidée par son chef)',
  partner_unit_id INT UNSIGNED NOT NULL COMMENT 'unité liée, hors filiation de l''ORBAT',
  kind VARCHAR(12) NOT NULL COMMENT 'appui|partenaire|rattachement',
  direction VARCHAR(8) NOT NULL DEFAULT 'mutual' COMMENT 'rôle de unit_id : out|in|mutual',
  starts_on DATE DEFAULT NULL,
  ends_on DATE DEFAULT NULL,
  note VARCHAR(200) NOT NULL DEFAULT '',
  grant_read TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'les membres de partner_unit_id lisent l''espace de unit_id',
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ended_at DATETIME DEFAULT NULL,
  ended_by INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_jnet_unit_links_unit (tenant_id, unit_id, ended_at),
  KEY idx_jnet_unit_links_partner (tenant_id, partner_unit_id, ended_at),
  CONSTRAINT fk_jnet_unit_links_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS jnet_links (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  src_type VARCHAR(10) NOT NULL COMMENT 'echange|page|tache|operation|cible|document|unite|membre',
  src_id VARCHAR(40) NOT NULL,
  dst_type VARCHAR(10) NOT NULL,
  dst_id VARCHAR(40) NOT NULL,
  space_id INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'espace d''où le lien a été posé (0 = hors espace) : son chef peut le retirer',
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_jnet_links_pair (tenant_id, src_type, src_id, dst_type, dst_id),
  KEY idx_jnet_links_src (tenant_id, src_type, src_id),
  KEY idx_jnet_links_dst (tenant_id, dst_type, dst_id),
  KEY idx_jnet_links_space (tenant_id, space_id),
  CONSTRAINT fk_jnet_links_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
