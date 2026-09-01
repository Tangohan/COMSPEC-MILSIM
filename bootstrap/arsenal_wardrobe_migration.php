<?php

declare(strict_types=1);

/**
 * Wardrobes ACE Arsenal synchronisées Athena + collections d’équipement.
 */
return static function (PDO $pdo): void {
    $tableExists = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };
    $columnExists = static function (string $table, string $column) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    if (!$tableExists('arsenal_equipment_collections')) {
        $pdo->exec(
            "CREATE TABLE arsenal_equipment_collections (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                owner_user_id INT UNSIGNED DEFAULT NULL,
                name VARCHAR(120) NOT NULL,
                slug VARCHAR(140) NOT NULL,
                description VARCHAR(500) DEFAULT NULL,
                cover_image_path VARCHAR(255) DEFAULT NULL,
                visibility ENUM('personal','unit','tenant') NOT NULL DEFAULT 'personal',
                tags_json JSON DEFAULT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_arsenal_coll_tenant_slug (tenant_id, slug),
                KEY idx_arsenal_coll_owner (tenant_id, owner_user_id),
                KEY idx_arsenal_coll_visibility (tenant_id, visibility),
                CONSTRAINT fk_arsenal_coll_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_arsenal_coll_owner FOREIGN KEY (owner_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$tableExists('arsenal_wardrobes')) {
        $pdo->exec(
            "CREATE TABLE arsenal_wardrobes (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                steam_uid VARCHAR(32) DEFAULT NULL,
                collection_id BIGINT UNSIGNED DEFAULT NULL,
                name VARCHAR(120) NOT NULL,
                slug VARCHAR(140) NOT NULL,
                source VARCHAR(40) NOT NULL DEFAULT 'ace_arsenal',
                payload_format VARCHAR(24) NOT NULL DEFAULT 'arma_loadout_str',
                payload_text MEDIUMTEXT NOT NULL,
                payload_sha256 CHAR(64) NOT NULL,
                notes VARCHAR(255) DEFAULT NULL,
                cover_image_path VARCHAR(255) DEFAULT NULL,
                is_favorite TINYINT(1) NOT NULL DEFAULT 0,
                last_synced_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_arsenal_wardrobe_user_slug (tenant_id, user_id, slug),
                KEY idx_arsenal_wardrobe_steam (tenant_id, steam_uid),
                KEY idx_arsenal_wardrobe_collection (collection_id),
                KEY idx_arsenal_wardrobe_updated (tenant_id, user_id, updated_at),
                CONSTRAINT fk_arsenal_wardrobe_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_arsenal_wardrobe_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_arsenal_wardrobe_collection FOREIGN KEY (collection_id) REFERENCES arsenal_equipment_collections (id) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$tableExists('arsenal_collection_wardrobes')) {
        $pdo->exec(
            "CREATE TABLE arsenal_collection_wardrobes (
                collection_id BIGINT UNSIGNED NOT NULL,
                wardrobe_id BIGINT UNSIGNED NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                PRIMARY KEY (collection_id, wardrobe_id),
                KEY idx_arsenal_cw_wardrobe (wardrobe_id),
                CONSTRAINT fk_arsenal_cw_collection FOREIGN KEY (collection_id) REFERENCES arsenal_equipment_collections (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_arsenal_cw_wardrobe FOREIGN KEY (wardrobe_id) REFERENCES arsenal_wardrobes (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if ($tableExists('arsenal_equipment_collections') && !$columnExists('arsenal_equipment_collections', 'cover_image_path')) {
        $pdo->exec('ALTER TABLE arsenal_equipment_collections ADD COLUMN cover_image_path VARCHAR(255) DEFAULT NULL AFTER description');
    }
    if ($tableExists('arsenal_wardrobes') && !$columnExists('arsenal_wardrobes', 'cover_image_path')) {
        $pdo->exec('ALTER TABLE arsenal_wardrobes ADD COLUMN cover_image_path VARCHAR(255) DEFAULT NULL AFTER notes');
    }
};
