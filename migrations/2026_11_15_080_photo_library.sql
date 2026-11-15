-- Photothèque : étiquetage manuel des visages (aucune reconnaissance automatique), zones masquées appliquées côté serveur,
-- commentaires ancrés, étiquettes libres, état du fichier d'origine gardé à part quand un masque existe, versements au
-- dossier SSE. Les photos elles-mêmes restent dans recon_images (photos terrain envoyées par l'ATAK). Retour arrière :
-- migrations/rollback/photo_library_down.sql.

CREATE TABLE IF NOT EXISTS photo_library_tags (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  photo_id INT UNSIGNED NOT NULL COMMENT 'recon_images.id',
  x DECIMAL(7,6) NOT NULL,
  y DECIMAL(7,6) NOT NULL,
  w DECIMAL(7,6) NOT NULL,
  h DECIMAL(7,6) NOT NULL,
  target_type VARCHAR(8) NOT NULL COMMENT 'member|sse|unknown',
  target_id INT UNSIGNED DEFAULT NULL COMMENT 'users.id (member) ou sse_identities.id (sse)',
  author_user_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_photo_library_tags_photo (tenant_id, photo_id),
  KEY idx_photo_library_tags_target (tenant_id, target_type, target_id),
  CONSTRAINT fk_photo_library_tags_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS photo_library_masks (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  photo_id INT UNSIGNED NOT NULL,
  x DECIMAL(7,6) NOT NULL,
  y DECIMAL(7,6) NOT NULL,
  w DECIMAL(7,6) NOT NULL,
  h DECIMAL(7,6) NOT NULL,
  mode VARCHAR(8) NOT NULL DEFAULT 'blur' COMMENT 'blur|pixel|fill',
  author_user_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_photo_library_masks_photo (tenant_id, photo_id),
  CONSTRAINT fk_photo_library_masks_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS photo_library_comments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  photo_id INT UNSIGNED NOT NULL,
  body TEXT NOT NULL,
  anchor_kind VARCHAR(4) NOT NULL DEFAULT 'none' COMMENT 'none|zone|tag',
  anchor_x DECIMAL(7,6) DEFAULT NULL,
  anchor_y DECIMAL(7,6) DEFAULT NULL,
  anchor_w DECIMAL(7,6) DEFAULT NULL,
  anchor_h DECIMAL(7,6) DEFAULT NULL,
  anchor_tag_id INT UNSIGNED DEFAULT NULL,
  mentions VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'identifiants des membres cités, séparés par des virgules',
  author_user_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  edited_at DATETIME DEFAULT NULL,
  deleted_at DATETIME DEFAULT NULL,
  deleted_by INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_photo_library_comments_photo (tenant_id, photo_id, deleted_at),
  CONSTRAINT fk_photo_library_comments_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS photo_library_labels (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  photo_id INT UNSIGNED NOT NULL,
  label VARCHAR(40) NOT NULL,
  label_norm VARCHAR(40) NOT NULL,
  author_user_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_photo_library_labels (tenant_id, photo_id, label_norm),
  KEY idx_photo_library_labels_norm (tenant_id, label_norm),
  CONSTRAINT fk_photo_library_labels_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS photo_library_state (
  photo_id INT UNSIGNED NOT NULL,
  tenant_id INT UNSIGNED NOT NULL,
  original_path VARCHAR(255) DEFAULT NULL COMMENT 'fichier d''origine gardé hors de la racine web tant que des masques existent',
  zones_hash CHAR(40) DEFAULT NULL COMMENT 'empreinte des zones appliquées au fichier servi',
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (photo_id),
  KEY idx_photo_library_state_tenant (tenant_id),
  CONSTRAINT fk_photo_library_state_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS photo_library_sse (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  photo_id INT UNSIGNED NOT NULL,
  sse_photo_id INT UNSIGNED DEFAULT NULL COMMENT 'sse_file_photos.id créé par le versement',
  identity_id INT UNSIGNED DEFAULT NULL COMMENT 'fiche SSE à laquelle la photo a été rattachée',
  actor_user_id INT UNSIGNED NOT NULL,
  prev_confidential TINYINT(1) NOT NULL DEFAULT 0,
  prev_sse TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  withdrawn_at DATETIME DEFAULT NULL,
  withdrawn_by INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_photo_library_sse_photo (tenant_id, photo_id, withdrawn_at),
  CONSTRAINT fk_photo_library_sse_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
