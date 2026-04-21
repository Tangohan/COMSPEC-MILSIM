<?php

declare(strict_types=1);

/**
 * Pages HTML autonomes (livrets / maquettes) par communauté — idempotent.
 */
return static function (PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS training_formation_custom_pages (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            slug VARCHAR(120) NOT NULL,
            title VARCHAR(255) NOT NULL,
            html_body MEDIUMTEXT NOT NULL,
            is_published TINYINT(1) NOT NULL DEFAULT 0,
            created_by INT UNSIGNED NULL,
            updated_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_training_custom_page_slug (tenant_id, slug),
            KEY idx_training_custom_page_tenant (tenant_id),
            CONSTRAINT fk_training_custom_page_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
};
