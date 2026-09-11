<?php

declare(strict_types=1);

/**
 * Calques viewshed temporaires publiés depuis le jeu vers le poste.
 * Idempotent — appelée depuis run-migrations.php.
 */
return static function (PDO $pdo): void {
    if (!function_exists('schema_table_exists')) {
        require_once __DIR__ . '/schema_ensure_column.php';
    }

    if (schema_table_exists($pdo, 'atak_viewshed_overlays')) {
        return;
    }

    $pdo->exec(
        'CREATE TABLE atak_viewshed_overlays (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            map_id INT UNSIGNED NOT NULL DEFAULT 1,
            call_sign VARCHAR(64) NOT NULL DEFAULT \'\',
            center_x DOUBLE NOT NULL,
            center_y DOUBLE NOT NULL,
            radius_m DOUBLE NOT NULL DEFAULT 500,
            polygon_json MEDIUMTEXT NULL,
            expires_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_atak_viewshed_tenant_map (tenant_id, map_id, updated_at),
            CONSTRAINT atak_viewshed_overlays_tenant_fk
                FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
};
