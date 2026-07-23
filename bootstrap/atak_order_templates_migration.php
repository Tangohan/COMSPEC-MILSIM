<?php

declare(strict_types=1);

/**
 * Modèles d’ordres personnalisés (tenant) + libellé libre sur atak_orders.
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

    $columnExists = static function (PDO $pdo, string $table, string $column): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    if (!$tableExists($pdo, 'atak_order_templates')) {
        try {
            $pdo->exec(
                "CREATE TABLE atak_order_templates (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    tenant_id INT UNSIGNED NOT NULL,
                    label VARCHAR(120) NOT NULL,
                    default_payload VARCHAR(400) DEFAULT NULL,
                    created_by_user_id INT UNSIGNED DEFAULT NULL,
                    sort_order INT NOT NULL DEFAULT 0,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_atak_order_tpl_tenant (tenant_id, sort_order, id),
                    CONSTRAINT fk_atak_order_tpl_tenant FOREIGN KEY (tenant_id)
                        REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            echo "  [OK] atak_order_templates\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] atak_order_templates : ' . $e->getMessage() . "\n";
        }
    } else {
        echo "  [OK] atak_order_templates (déjà présente)\n";
    }

    if ($tableExists($pdo, 'atak_orders') && !$columnExists($pdo, 'atak_orders', 'type_label')) {
        try {
            $pdo->exec(
                "ALTER TABLE atak_orders
                 ADD COLUMN type_label VARCHAR(120) DEFAULT NULL AFTER order_type"
            );
            echo "  [OK] atak_orders.type_label\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] atak_orders.type_label : ' . $e->getMessage() . "\n";
        }
    } elseif ($tableExists($pdo, 'atak_orders')) {
        echo "  [OK] atak_orders.type_label (déjà présente)\n";
    }
};
