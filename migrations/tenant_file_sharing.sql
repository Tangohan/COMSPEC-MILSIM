-- Partage de fichiers d'une communauté (/partage, /back-office/partage) : dossiers, fichiers et autorisations.
-- Rien n'est visible par défaut : un dossier ou un fichier n'est accessible qu'à son propriétaire et à ceux à qui il
-- est partagé (membre, rôle, unité ou toute la communauté), au niveau Consulter, Déposer ou Gérer.
-- Les fichiers sont rangés hors racine web (storage/private/partage/{communauté}/…) et servis par le contrôleur.

CREATE TABLE IF NOT EXISTS `tenant_share_folders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `parent_id` INT UNSIGNED NULL COMMENT 'Dossier parent (même communauté) ; NULL : dossier racine',
  `name` VARCHAR(120) NOT NULL,
  `description` VARCHAR(500) NULL,
  `owner_user_id` INT UNSIGNED NOT NULL COMMENT 'Créateur : niveau Gérer sur le dossier et tout son contenu',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL COMMENT 'Corbeille : contenu inaccessible, restaurable',
  `deleted_by` INT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tsf_tenant_parent` (`tenant_id`, `parent_id`, `deleted_at`),
  KEY `idx_tsf_owner` (`tenant_id`, `owner_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tenant_share_files` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `folder_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL COMMENT 'Nom affiché et proposé au téléchargement',
  `stored_path` VARCHAR(255) NOT NULL COMMENT 'Relatif à storage/private, nom aléatoire',
  `mime` VARCHAR(120) NOT NULL DEFAULT 'application/octet-stream',
  `size_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `sha256` CHAR(64) NULL,
  `uploaded_by` INT UNSIGNED NOT NULL,
  `download_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL,
  `deleted_by` INT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tsfi_folder` (`tenant_id`, `folder_id`, `deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tenant_share_grants` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `folder_id` INT UNSIGNED NULL COMMENT 'Partage d''un dossier (hérité par les sous-dossiers et fichiers)',
  `file_id` INT UNSIGNED NULL COMMENT 'Partage d''un seul fichier',
  `target_type` VARCHAR(10) NOT NULL COMMENT 'user, role, unit ou all',
  `target_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 pour all',
  `level` VARCHAR(10) NOT NULL DEFAULT 'view' COMMENT 'view (consulter), upload (déposer), manage (gérer)',
  `expires_at` DATETIME NULL,
  `granted_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tsg_folder` (`tenant_id`, `folder_id`),
  KEY `idx_tsg_file` (`tenant_id`, `file_id`),
  KEY `idx_tsg_target` (`tenant_id`, `target_type`, `target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tenant_share_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `folder_id` INT UNSIGNED NULL,
  `file_id` INT UNSIGNED NULL,
  `action` VARCHAR(24) NOT NULL COMMENT 'create, upload, download, rename, delete, restore, share, unshare, takeover',
  `detail` VARCHAR(255) NULL,
  `ip` VARCHAR(45) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tse_folder` (`tenant_id`, `folder_id`, `created_at`),
  KEY `idx_tse_file` (`tenant_id`, `file_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
