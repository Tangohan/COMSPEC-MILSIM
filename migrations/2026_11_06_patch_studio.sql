-- Additif, sans modification des tables ATHENA existantes.
CREATE TABLE IF NOT EXISTS patch_studio_projects (
 id CHAR(32) NOT NULL PRIMARY KEY, tenant_id INT NOT NULL, owner_id INT NOT NULL,
 name VARCHAR(120) NOT NULL, visibility VARCHAR(16) NOT NULL DEFAULT 'private',
 status VARCHAR(16) NOT NULL DEFAULT 'active', revision INT NOT NULL DEFAULT 1,
 document MEDIUMTEXT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
 INDEX ps_tenant_owner (tenant_id, owner_id, status), INDEX ps_tenant_visibility (tenant_id, visibility, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS patch_studio_versions (
 project_id CHAR(32) NOT NULL, revision INT NOT NULL, author_id INT NOT NULL,
 document MEDIUMTEXT NOT NULL, created_at DATETIME NOT NULL,
 PRIMARY KEY (project_id, revision)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS patch_studio_shares (
 project_id CHAR(32) NOT NULL, user_id INT NOT NULL, role VARCHAR(8) NOT NULL,
 PRIMARY KEY (project_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS patch_studio_assets (
 id CHAR(32) NOT NULL PRIMARY KEY, tenant_id INT NOT NULL, owner_id INT NOT NULL,
 hash CHAR(64) NOT NULL, mime VARCHAR(80) NOT NULL, bytes INT NOT NULL,
 label VARCHAR(120) NOT NULL, license_note VARCHAR(500) NOT NULL DEFAULT '', created_at DATETIME NOT NULL,
 INDEX ps_asset_owner (tenant_id, owner_id), UNIQUE KEY ps_asset_dedupe (tenant_id, owner_id, hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS patch_studio_asset_refs (
 project_id CHAR(32) NOT NULL, asset_id CHAR(32) NOT NULL,
 PRIMARY KEY (project_id, asset_id), INDEX ps_refs_asset (asset_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS patch_studio_releases (
 project_id CHAR(32) NOT NULL, revision INT NOT NULL, variant VARCHAR(8) NOT NULL,
 png_hash CHAR(64) NOT NULL, preview_hash CHAR(64) NOT NULL, paa_hash CHAR(64) NOT NULL, width INT NOT NULL, height INT NOT NULL,
 created_at DATETIME NOT NULL, PRIMARY KEY (project_id, revision, variant)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
