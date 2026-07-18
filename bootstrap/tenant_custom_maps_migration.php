<?php

declare(strict_types=1);

/**
 * Cartes custom communauté (fond image) pour Overwatch / TACMAP.
 * Idempotent — appelée depuis run-migrations.php.
 */
return function (PDO $pdo): void {
    $exists = $pdo->query(
        "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_custom_maps' LIMIT 1"
    );
    if ($exists && $exists->fetchColumn()) {
        return;
    }

    echo "Migration tenant_custom_maps...\n";
    try {
        $pdo->exec(
            "CREATE TABLE tenant_custom_maps (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                created_by INT UNSIGNED NOT NULL,
                map_id INT UNSIGNED NOT NULL,
                label VARCHAR(120) NOT NULL,
                slug VARCHAR(80) NOT NULL,
                image_path VARCHAR(512) NOT NULL,
                image_width INT UNSIGNED NOT NULL,
                image_height INT UNSIGNED NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY tenant_custom_maps_map_id (map_id),
                UNIQUE KEY tenant_custom_maps_tenant_slug (tenant_id, slug),
                KEY tenant_custom_maps_tenant_active (tenant_id, is_active),
                CONSTRAINT tenant_custom_maps_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        echo "tenant_custom_maps OK.\n";
    } catch (PDOException $e) {
        echo '  [ATTENTION] tenant_custom_maps : ' . $e->getMessage() . "\n";
    }
};
