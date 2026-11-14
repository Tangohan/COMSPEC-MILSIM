-- JNET : outils de travail de l'espace d'une unité (tâches, pages écrites ensemble avec réactions, commentaires et
-- sondages). Les données viennent des membres de l'unité ; rien n'est pré-rempli. Retour arrière :
-- migrations/rollback/jnet_unit_workspace_down.sql (supprime ces cinq tables et la colonne hidden_modules).

CREATE TABLE IF NOT EXISTS jnet_unit_tasks (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  unit_id INT UNSIGNED NOT NULL,
  title VARCHAR(200) NOT NULL,
  status VARCHAR(8) NOT NULL DEFAULT 'todo' COMMENT 'todo|doing|done',
  assignee_user_id INT UNSIGNED DEFAULT NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_jnet_unit_tasks_unit (tenant_id, unit_id, status),
  KEY idx_jnet_unit_tasks_assignee (tenant_id, assignee_user_id, status),
  CONSTRAINT fk_jnet_unit_tasks_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS jnet_unit_pages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  unit_id INT UNSIGNED NOT NULL,
  title VARCHAR(160) NOT NULL DEFAULT '',
  blocks MEDIUMTEXT NOT NULL COMMENT 'JSON : liste de blocs {id, t, text, done, opts}',
  sort_order INT NOT NULL DEFAULT 0,
  created_by INT UNSIGNED NOT NULL,
  updated_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_jnet_unit_pages_unit (tenant_id, unit_id, deleted_at, sort_order),
  CONSTRAINT fk_jnet_unit_pages_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS jnet_unit_page_reactions (
  page_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  kind VARCHAR(12) NOT NULL COMMENT 'vu|recu|question',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (page_id, user_id, kind),
  CONSTRAINT fk_jnet_unit_page_reactions_page FOREIGN KEY (page_id) REFERENCES jnet_unit_pages (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS jnet_unit_page_comments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  page_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  body VARCHAR(1000) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_jnet_unit_page_comments_page (page_id, id),
  CONSTRAINT fk_jnet_unit_page_comments_page FOREIGN KEY (page_id) REFERENCES jnet_unit_pages (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS jnet_unit_page_votes (
  page_id INT UNSIGNED NOT NULL,
  block_id VARCHAR(12) NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  option_idx TINYINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (page_id, block_id, user_id),
  CONSTRAINT fk_jnet_unit_page_votes_page FOREIGN KEY (page_id) REFERENCES jnet_unit_pages (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
