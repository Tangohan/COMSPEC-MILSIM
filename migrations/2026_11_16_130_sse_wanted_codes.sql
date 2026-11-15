-- SSE avis de recherche : codification des motifs et des mesures, signalement physique (instantané daté) et pièces jointes
-- (images rangées dans l'espace privé, jamais sous public/). Deux tables NOUVELLES, filles de sse_wanted_notices : aucune
-- colonne ajoutée ni donnée modifiée ; un avis sans ligne ici fonctionne comme avant. Sans ces tables, le formulaire place
-- l'avis sans codes ni pièces et l'écrit clairement.
-- Retour arrière : migrations/rollback/sse_wanted_codes_down.sql.

CREATE TABLE IF NOT EXISTS sse_wanted_notice_extras (
  notice_id INT UNSIGNED NOT NULL,
  tenant_id INT UNSIGNED NOT NULL,
  codes_json TEXT NULL COMMENT 'motifs et mesures choisis : codes et libellés du jour de la pose',
  physical_json TEXT NULL COMMENT 'signalement physique propre à l''avis (instantané daté)',
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (notice_id),
  KEY idx_sse_wanted_extras_tenant (tenant_id, notice_id),
  CONSTRAINT fk_sse_wanted_extras_notice FOREIGN KEY (notice_id) REFERENCES sse_wanted_notices (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sse_wanted_attachments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  notice_id INT UNSIGNED NOT NULL,
  identity_id INT UNSIGNED NOT NULL,
  kind VARCHAR(12) NOT NULL DEFAULT 'other' COMMENT 'portrait|mark|vehicle|place|other',
  caption VARCHAR(160) NULL,
  path VARCHAR(160) NOT NULL COMMENT 'chemin privé sse-private/sse-wanted/<communauté>_<aléa>.<ext>',
  thumb_path VARCHAR(160) NULL,
  mime VARCHAR(20) NOT NULL,
  width INT UNSIGNED NULL,
  height INT UNSIGNED NULL,
  bytes INT UNSIGNED NULL,
  sha1 CHAR(40) NULL,
  is_main TINYINT UNSIGNED NOT NULL DEFAULT 0,
  source_photo_id INT UNSIGNED NULL COMMENT 'photo de la photothèque dont l''image est une copie',
  created_by_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_sse_wanted_att_notice (tenant_id, notice_id, id),
  CONSTRAINT fk_sse_wanted_att_notice FOREIGN KEY (notice_id) REFERENCES sse_wanted_notices (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
