<?php

declare(strict_types=1);

/**
 * Types d’ordres personnalisés par tenant (libellés affichés dans le sélecteur).
 * Idempotent.
 */
return static function (PDO $pdo): void {
    $tableExists = static function (PDO $pdo, string $table): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if (!$tableExists($pdo, 'atak_order_types')) {
        try {
            $pdo->exec(
                "CREATE TABLE atak_order_types (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    tenant_id INT UNSIGNED NOT NULL,
                    label VARCHAR(120) NOT NULL,
                    description VARCHAR(400) DEFAULT NULL,
                    created_by_user_id INT UNSIGNED DEFAULT NULL,
                    sort_order INT NOT NULL DEFAULT 0,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_atak_order_types_tenant (tenant_id, sort_order, id),
                    CONSTRAINT fk_atak_order_types_tenant FOREIGN KEY (tenant_id)
                        REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            echo "  [OK] atak_order_types\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] atak_order_types : ' . $e->getMessage() . "\n";
        }
    } else {
        echo "  [OK] atak_order_types (déjà présente)\n";
    }
};
