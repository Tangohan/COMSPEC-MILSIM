<?php

declare(strict_types=1);

/**
 * Tenues mises en avant sur le tableau de bord. Idempotent.
 */
return static function (PDO $pdo): void {
    $tableExists = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if ($tableExists('tenant_dashboard_wardrobe_pins')) {
        return;
    }
    if (!$tableExists('arsenal_wardrobes') || !$tableExists('tenants') || !$tableExists('users')) {
        return;
    }

    $pdo->exec(
        'CREATE TABLE tenant_dashboard_wardrobe_pins (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            wardrobe_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(200) DEFAULT NULL,
            badge_label VARCHAR(80) DEFAULT NULL,
            figure_path VARCHAR(255) DEFAULT NULL,
            backdrop_path VARCHAR(255) DEFAULT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_by INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_tdwp_tenant_wardrobe (tenant_id, wardrobe_id),
            KEY idx_tdwp_tenant_sort (tenant_id, sort_order),
            CONSTRAINT tdwp_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
            CONSTRAINT tdwp_wardrobe_fk FOREIGN KEY (wardrobe_id) REFERENCES arsenal_wardrobes (id) ON DELETE CASCADE,
            CONSTRAINT tdwp_created_by_fk FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    echo "Table tenant_dashboard_wardrobe_pins créée.\n";
};
