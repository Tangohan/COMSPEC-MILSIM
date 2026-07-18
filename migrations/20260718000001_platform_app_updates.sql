-- Mises à jour applicatives (packages ZIP) — distinct des canaux modules plateforme.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS platform_app_releases (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    version VARCHAR(32) NOT NULL,
    minimum_version VARCHAR(32) NULL,
    status ENUM(
        'uploaded',
        'validated',
        'previewed',
        'deploying',
        'deployed',
        'failed',
        'rolled_back'
    ) NOT NULL DEFAULT 'uploaded',
    package_path VARCHAR(512) NOT NULL,
    extract_path VARCHAR(512) NULL,
    payload_checksum CHAR(64) NULL,
    manifest_json JSON NULL,
    maintenance_required TINYINT(1) NOT NULL DEFAULT 1,
    uploaded_by BIGINT UNSIGNED NULL,
    deployed_by BIGINT UNSIGNED NULL,
    deployed_at DATETIME NULL,
    rolled_back_at DATETIME NULL,
    error_message TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_platform_app_releases_version (version),
    KEY idx_platform_app_releases_status (status),
    KEY idx_platform_app_releases_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS platform_app_release_files (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    release_id BIGINT UNSIGNED NOT NULL,
    relative_path VARCHAR(512) NOT NULL,
    action ENUM('add', 'update', 'delete', 'unchanged') NOT NULL,
    source_checksum CHAR(64) NULL,
    target_checksum CHAR(64) NULL,
    conflict TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_platform_app_release_files_release (release_id),
    CONSTRAINT fk_platform_app_release_files_release
        FOREIGN KEY (release_id) REFERENCES platform_app_releases (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS platform_app_deployment_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    release_id BIGINT UNSIGNED NULL,
    action VARCHAR(64) NOT NULL,
    level ENUM('info', 'warning', 'error') NOT NULL DEFAULT 'info',
    message TEXT NOT NULL,
    context_json JSON NULL,
    actor_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_platform_app_deployment_logs_release (release_id),
    KEY idx_platform_app_deployment_logs_created (created_at),
    CONSTRAINT fk_platform_app_deployment_logs_release
        FOREIGN KEY (release_id) REFERENCES platform_app_releases (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS platform_app_deployment_backups (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    release_id BIGINT UNSIGNED NOT NULL,
    backup_path VARCHAR(512) NOT NULL,
    previous_version VARCHAR(32) NULL,
    file_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_platform_app_deployment_backups_release (release_id),
    CONSTRAINT fk_platform_app_deployment_backups_release
        FOREIGN KEY (release_id) REFERENCES platform_app_releases (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS platform_app_deployment_locks (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
    locked_by BIGINT UNSIGNED NULL,
    release_id BIGINT UNSIGNED NULL,
    locked_at DATETIME NULL,
    expires_at DATETIME NULL,
    CONSTRAINT chk_platform_app_deployment_locks_singleton CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO platform_app_deployment_locks (id, locked_by, release_id, locked_at, expires_at)
VALUES (1, NULL, NULL, NULL, NULL);
