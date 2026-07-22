<?php

declare(strict_types=1);

/**
 * Ordres C2 ATAK (émission web / réception jeu via messagerie ORDER|…).
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

    if ($tableExists($pdo, 'atak_orders')) {
        return;
    }

    try {
        $pdo->exec(
            "CREATE TABLE atak_orders (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                map_id INT UNSIGNED NOT NULL DEFAULT 1,
                external_id VARCHAR(64) NOT NULL,
                parent_external_id VARCHAR(64) DEFAULT NULL,
                order_type VARCHAR(32) NOT NULL DEFAULT 'MOVE',
                target VARCHAR(128) DEFAULT NULL,
                payload TEXT,
                priority VARCHAR(32) NOT NULL DEFAULT 'IMPORTANT',
                issuer VARCHAR(128) NOT NULL,
                status VARCHAR(32) NOT NULL DEFAULT 'PENDING',
                note VARCHAR(500) DEFAULT NULL,
                status_by VARCHAR(128) DEFAULT NULL,
                source ENUM('game','web') NOT NULL DEFAULT 'web',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_atak_orders_tenant_map_ext (tenant_id, map_id, external_id),
                KEY idx_atak_orders_tenant_map_updated (tenant_id, map_id, updated_at),
                KEY idx_atak_orders_tenant_status (tenant_id, map_id, status),
                CONSTRAINT fk_atak_orders_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        echo "  [OK] atak_orders\n";
    } catch (Throwable $e) {
        echo '  [ATTENTION] atak_orders : ' . $e->getMessage() . "\n";
    }
};
