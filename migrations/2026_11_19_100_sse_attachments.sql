-- SSE : fenêtre « Rattacher » universelle. Trois tables, aucune colonne ajoutée ni donnée modifiée.
--
--  sse_link_attachments : un rattachement EST un lien de sse_entity_links, enrichi ici (nature en clair, libellé donné à la
--    pièce dans la cible, motif, étiquettes, note, alignement de confidentialité demandé, auteur, dates, statut actif/retiré).
--    link_id renvoie au lien tant que le rattachement est actif ; il est vidé au détachement (le lien est retiré, la ligne
--    reste comme historique). Un seul rattachement par lien.
--  sse_attach_tags : catalogue des étiquettes de la communauté (nom, couleur sobre).
--  sse_leads : pistes (entité légère : référence, titre, description, état, classification, responsable).
--
-- Sans ces tables, la fenêtre fonctionne en lecture et pour le rattachement historique à un dossier ; le reste est désactivé
-- avec un message. Retour arrière : migrations/rollback/sse_attachments_down.sql.

CREATE TABLE IF NOT EXISTS sse_link_attachments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  link_id INT UNSIGNED NULL COMMENT 'sse_entity_links.id tant que le rattachement est actif',
  source_type VARCHAR(24) NOT NULL COMMENT 'la pièce rattachée',
  source_id VARCHAR(40) NOT NULL,
  target_type VARCHAR(24) NOT NULL COMMENT 'la cible (dossier, personne, piste...)',
  target_id VARCHAR(40) NOT NULL,
  nature VARCHAR(32) NOT NULL DEFAULT 'filed_in' COMMENT 'filed_in|concerns|evidence_of|mentions|created_from|verify_with|related',
  nature_label VARCHAR(80) NOT NULL DEFAULT '' COMMENT 'libellé libre de la nature',
  label VARCHAR(160) NOT NULL DEFAULT '' COMMENT 'nom donné à la pièce dans la cible (la pièce ne change pas)',
  reason VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'motif du rattachement',
  note VARCHAR(500) NOT NULL DEFAULT '',
  tags VARCHAR(500) NOT NULL DEFAULT '' COMMENT 'étiquettes : identifiants du catalogue entre virgules (,3,12,)',
  align_requested TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1 : aligner la confidentialité de la pièce sur celle de la cible',
  align_from VARCHAR(16) NULL COMMENT 'niveau de la pièce avant alignement',
  align_to VARCHAR(16) NULL COMMENT 'niveau posé par alignement',
  status VARCHAR(12) NOT NULL DEFAULT 'active' COMMENT 'active|removed',
  created_by_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_by_user_id INT UNSIGNED NULL,
  updated_at DATETIME NULL,
  removed_by_user_id INT UNSIGNED NULL,
  removed_at DATETIME NULL,
  removed_reason VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  UNIQUE KEY uq_sse_link_attachment (tenant_id, link_id),
  KEY idx_sse_link_attachment_src (tenant_id, source_type, source_id, status),
  KEY idx_sse_link_attachment_dst (tenant_id, target_type, target_id, status),
  CONSTRAINT fk_sse_link_attachment_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sse_attach_tags (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  name VARCHAR(40) NOT NULL,
  slug VARCHAR(40) NOT NULL COMMENT 'nom sans accents ni majuscules : unicité et recherche',
  color VARCHAR(12) NOT NULL DEFAULT 'slate' COMMENT 'slate|green|blue|amber|red|violet',
  created_by_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sse_attach_tag (tenant_id, slug),
  CONSTRAINT fk_sse_attach_tag_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sse_leads (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  reference_code VARCHAR(32) NOT NULL,
  title VARCHAR(200) NOT NULL DEFAULT '',
  description TEXT NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'ouverte' COMMENT 'ouverte|suivie|close',
  classification VARCHAR(16) NOT NULL DEFAULT 'encadrement' COMMENT 'interne|encadrement|confidentiel|tres_restreint',
  owner_user_id INT UNSIGNED NULL COMMENT 'responsable',
  created_by_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sse_lead_ref (tenant_id, reference_code),
  KEY idx_sse_lead_status (tenant_id, status),
  CONSTRAINT fk_sse_lead_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
