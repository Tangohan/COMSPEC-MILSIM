<?php

declare(strict_types=1);

/**
 * Ordres C2 ATAK v2 : destinataires structurés, ACK/annulation, délais radio fictifs,
 * identifiants militaires stables par terminal / opérateur.
 * Idempotent (ALTER + CREATE IF NOT EXISTS).
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

    $addColumn = static function (PDO $pdo, string $table, string $column, string $ddl) use ($columnExists): void {
        if ($columnExists($pdo, $table, $column)) {
            return;
        }
        try {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN {$ddl}");
            echo "  [OK] {$table}.{$column}\n";
        } catch (Throwable $e) {
            echo "  [ATTENTION] {$table}.{$column} : " . $e->getMessage() . "\n";
        }
    };

    if (!$tableExists($pdo, 'atak_orders')) {
        echo "  [ATTENTION] atak_orders absente — lancer d’abord atak_orders_migration\n";

        return;
    }

    $addColumn(
        $pdo,
        'atak_orders',
        'target_type',
        "target_type VARCHAR(32) NOT NULL DEFAULT 'all' AFTER target"
    );
    $addColumn(
        $pdo,
        'atak_orders',
        'target_ref',
        'target_ref VARCHAR(128) DEFAULT NULL AFTER target_type'
    );
    $addColumn(
        $pdo,
        'atak_orders',
        'target_label',
        'target_label VARCHAR(160) DEFAULT NULL AFTER target_ref'
    );
    $addColumn(
        $pdo,
        'atak_orders',
        'issuer_user_id',
        'issuer_user_id INT UNSIGNED DEFAULT NULL AFTER issuer'
    );
    $addColumn(
        $pdo,
        'atak_orders',
        'ack_at',
        'ack_at DATETIME DEFAULT NULL AFTER status_by'
    );
    $addColumn(
        $pdo,
        'atak_orders',
        'ack_by',
        'ack_by VARCHAR(128) DEFAULT NULL AFTER ack_at'
    );
    $addColumn(
        $pdo,
        'atak_orders',
        'cancelled_at',
        'cancelled_at DATETIME DEFAULT NULL AFTER ack_by'
    );
    $addColumn(
        $pdo,
        'atak_orders',
        'cancelled_by',
        'cancelled_by VARCHAR(128) DEFAULT NULL AFTER cancelled_at'
    );
    $addColumn(
        $pdo,
        'atak_orders',
        'deliver_at',
        'deliver_at DATETIME DEFAULT NULL AFTER cancelled_by'
    );
    $addColumn(
        $pdo,
        'atak_orders',
        'ack_deadline_at',
        'ack_deadline_at DATETIME DEFAULT NULL AFTER deliver_at'
    );
    $addColumn(
        $pdo,
        'atak_orders',
        'radio_sim',
        'radio_sim TINYINT(1) NOT NULL DEFAULT 1 AFTER ack_deadline_at'
    );
    $addColumn(
        $pdo,
        'atak_orders',
        'sim_state',
        "sim_state VARCHAR(32) NOT NULL DEFAULT 'delivered' AFTER radio_sim"
    );
    $addColumn(
        $pdo,
        'atak_orders',
        'sim_latency_sec',
        'sim_latency_sec SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER sim_state'
    );
    $addColumn(
        $pdo,
        'atak_orders',
        'sim_event',
        'sim_event VARCHAR(64) DEFAULT NULL AFTER sim_latency_sec'
    );

    if ($tableExists($pdo, 'atak_units')) {
        $addColumn(
            $pdo,
            'atak_units',
            'military_id',
            'military_id VARCHAR(32) DEFAULT NULL AFTER call_sign'
        );
        try {
            $st = $pdo->query(
                "SELECT 1 FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'atak_units'
                   AND INDEX_NAME = 'idx_atak_units_tenant_military' LIMIT 1"
            );
            if ($st && !$st->fetchColumn()) {
                $pdo->exec(
                    'CREATE INDEX idx_atak_units_tenant_military
                     ON atak_units (tenant_id, military_id)'
                );
                echo "  [OK] atak_units.idx_atak_units_tenant_military\n";
            }
        } catch (Throwable $e) {
            echo '  [ATTENTION] index military_id atak_units : ' . $e->getMessage() . "\n";
        }
    }

    if (!$tableExists($pdo, 'atak_operator_ids')) {
        try {
            $pdo->exec(
                "CREATE TABLE atak_operator_ids (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    tenant_id INT UNSIGNED NOT NULL,
                    user_id INT UNSIGNED DEFAULT NULL,
                    call_sign VARCHAR(128) DEFAULT NULL,
                    military_id VARCHAR(32) NOT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uniq_atak_opid_tenant_mid (tenant_id, military_id),
                    UNIQUE KEY uniq_atak_opid_tenant_user (tenant_id, user_id),
                    KEY idx_atak_opid_tenant_callsign (tenant_id, call_sign),
                    CONSTRAINT fk_atak_opid_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id)
                        ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT fk_atak_opid_user FOREIGN KEY (user_id) REFERENCES users (id)
                        ON DELETE SET NULL ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            echo "  [OK] atak_operator_ids\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] atak_operator_ids : ' . $e->getMessage() . "\n";
        }
    }
};
