<?php

declare(strict_types=1);

/**
 * Carte tactique permanente d’un dossier SSE : vue (centre/zoom) + pings/marqueurs.
 * Distinct des pings ATAK live (mission) — ici tout est rattaché au dossier.
 */
return static function (PDO $pdo): void {
    $tableExists = static function (PDO $pdo, string $table): bool {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
        $stmt->execute([$table]);

        return (bool) $stmt->fetchColumn();
    };

    if (!$tableExists($pdo, 'sse_case_map_state')) {
        $pdo->exec("CREATE TABLE sse_case_map_state (
            case_id INT UNSIGNED NOT NULL,
            tenant_id INT UNSIGNED NOT NULL,
            map_id INT UNSIGNED NOT NULL DEFAULT 1,
            center_lat DOUBLE NOT NULL DEFAULT 48.8566,
            center_lng DOUBLE NOT NULL DEFAULT 2.3522,
            zoom TINYINT UNSIGNED NOT NULL DEFAULT 6,
            atak_layer_enabled TINYINT(1) NOT NULL DEFAULT 1,
            snapshot_meta JSON DEFAULT NULL,
            updated_by INT UNSIGNED DEFAULT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (case_id),
            KEY idx_sse_case_map_tenant (tenant_id, map_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    if (!$tableExists($pdo, 'sse_case_map_features')) {
        $pdo->exec("CREATE TABLE sse_case_map_features (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            case_id INT UNSIGNED NOT NULL,
            kind VARCHAR(16) NOT NULL DEFAULT 'ping',
            label VARCHAR(160) NOT NULL DEFAULT '',
            note VARCHAR(500) DEFAULT NULL,
            color VARCHAR(16) NOT NULL DEFAULT '#34d399',
            lat DOUBLE DEFAULT NULL,
            lng DOUBLE DEFAULT NULL,
            arma_x DOUBLE DEFAULT NULL,
            arma_y DOUBLE DEFAULT NULL,
            site_id INT UNSIGNED DEFAULT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_by INT UNSIGNED DEFAULT NULL,
            author_label VARCHAR(160) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_sse_case_map_feat_case (tenant_id, case_id),
            KEY idx_sse_case_map_feat_atak (tenant_id, case_id, arma_x, arma_y)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
};
