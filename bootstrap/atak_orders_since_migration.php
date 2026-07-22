<?php

declare(strict_types=1);

/**
 * Garantit updated_at + index filtrable pour le poll delta ordersIndex (?since=).
 * Idempotent — la table de base les crée déjà ; utile si schéma prod incomplet.
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

    $indexExists = static function (PDO $pdo, string $table, string $indexName): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $indexName]);

        return (bool) $st->fetchColumn();
    };

    if (!$tableExists($pdo, 'atak_orders')) {
        echo "  [ATTENTION] atak_orders absente — lancer d’abord atak_orders_migration\n";

        return;
    }

    if (!$columnExists($pdo, 'atak_orders', 'updated_at')) {
        try {
            $pdo->exec(
                'ALTER TABLE atak_orders
                 ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                 ON UPDATE CURRENT_TIMESTAMP AFTER created_at'
            );
            echo "  [OK] atak_orders.updated_at\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] atak_orders.updated_at : ' . $e->getMessage() . "\n";
        }
    } else {
        echo "  [OK] atak_orders.updated_at (déjà présente)\n";
    }

    if (!$indexExists($pdo, 'atak_orders', 'idx_atak_orders_tenant_map_updated')) {
        try {
            $pdo->exec(
                'CREATE INDEX idx_atak_orders_tenant_map_updated
                 ON atak_orders (tenant_id, map_id, updated_at)'
            );
            echo "  [OK] idx_atak_orders_tenant_map_updated\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] idx_atak_orders_tenant_map_updated : ' . $e->getMessage() . "\n";
        }
    } else {
        echo "  [OK] idx_atak_orders_tenant_map_updated (déjà présent)\n";
    }
};
