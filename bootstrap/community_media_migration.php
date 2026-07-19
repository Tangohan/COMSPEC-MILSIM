<?php

declare(strict_types=1);

/**
 * Galeries / médias publics des communautés (images, vidéos courtes, vidéos longues).
 *
 * @return callable(PDO): void
 */
return static function (PDO $pdo): void {
    $hasTable = static function (PDO $pdo, string $table): bool {
        $st = $pdo->prepare(
            "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1"
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if (!$hasTable($pdo, 'community_media_collections')) {
        try {
            $pdo->exec(
                "CREATE TABLE community_media_collections (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    tenant_id INT UNSIGNED NOT NULL,
                    title VARCHAR(180) NOT NULL,
                    description TEXT NULL,
                    is_public TINYINT(1) NOT NULL DEFAULT 0,
                    sort_order INT NOT NULL DEFAULT 0,
                    created_by INT UNSIGNED NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_cmc_tenant_public (tenant_id, is_public),
                    INDEX idx_cmc_tenant_sort (tenant_id, sort_order)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            echo "  [OK] community_media_collections\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] community_media_collections : ' . $e->getMessage() . "\n";
        }
    }

    if (!$hasTable($pdo, 'community_media_items')) {
        try {
            $pdo->exec(
                "CREATE TABLE community_media_items (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    tenant_id INT UNSIGNED NOT NULL,
                    collection_id BIGINT UNSIGNED NULL,
                    media_kind VARCHAR(32) NOT NULL,
                    title VARCHAR(180) NOT NULL DEFAULT '',
                    caption TEXT NULL,
                    storage_path VARCHAR(512) NULL,
                    external_url VARCHAR(1024) NULL,
                    mime_type VARCHAR(120) NULL,
                    file_size INT UNSIGNED NULL,
                    duration_seconds INT UNSIGNED NULL,
                    width INT UNSIGNED NULL,
                    height INT UNSIGNED NULL,
                    blur_mode VARCHAR(32) NOT NULL DEFAULT 'none',
                    blur_regions_json TEXT NULL,
                    show_on_public_page TINYINT(1) NOT NULL DEFAULT 0,
                    is_hero TINYINT(1) NOT NULL DEFAULT 0,
                    sort_order INT NOT NULL DEFAULT 0,
                    status VARCHAR(32) NOT NULL DEFAULT 'draft',
                    created_by INT UNSIGNED NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_cmi_tenant_public (tenant_id, show_on_public_page, status),
                    INDEX idx_cmi_tenant_collection (tenant_id, collection_id),
                    INDEX idx_cmi_tenant_kind (tenant_id, media_kind),
                    INDEX idx_cmi_tenant_sort (tenant_id, sort_order)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            echo "  [OK] community_media_items\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] community_media_items : ' . $e->getMessage() . "\n";
        }
    }
};
