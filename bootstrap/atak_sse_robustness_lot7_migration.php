<?php

declare(strict_types=1);

/**
 * LOT 7 — Robustesse SSE : outbox sync, conflits, verrous job.
 */
return static function (PDO $pdo, ?callable $log = null): void {
    $log ??= static function (string $m): void {
        // Silence web : run-migrations.php passe un $log explicite.
    };

    $tableExists = static function (PDO $pdo, string $table): bool {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table]);

        return (bool) $stmt->fetchColumn();
    };

    if (!$tableExists($pdo, 'tenants')) {
        $log("  [SKIP] tenants absente — LOT 7 différé\n");

        return;
    }

    if (!$tableExists($pdo, 'sse_sync_outbox')) {
        $pdo->exec(
            "CREATE TABLE sse_sync_outbox (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                uuid CHAR(36) NOT NULL,
                tenant_id INT UNSIGNED NOT NULL,
                idempotency_key VARCHAR(120) NOT NULL,
                channel VARCHAR(32) NOT NULL DEFAULT 'arma',
                payload_json MEDIUMTEXT NOT NULL,
                status VARCHAR(24) NOT NULL DEFAULT 'pending',
                attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
                last_error VARCHAR(500) DEFAULT NULL,
                acked_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_sse_outbox_idem (tenant_id, idempotency_key),
                UNIQUE KEY uniq_sse_outbox_uuid (tenant_id, uuid),
                KEY idx_sse_outbox_status (tenant_id, status, created_at),
                CONSTRAINT fk_sse_outbox_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $log("  [OK] sse_sync_outbox\n");
    } else {
        $log("  [OK] sse_sync_outbox (déjà présente)\n");
    }

    if (!$tableExists($pdo, 'sse_sync_conflicts')) {
        $pdo->exec(
            "CREATE TABLE sse_sync_conflicts (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                uuid CHAR(36) NOT NULL,
                tenant_id INT UNSIGNED NOT NULL,
                object_type VARCHAR(48) NOT NULL DEFAULT '',
                object_ref VARCHAR(120) NOT NULL DEFAULT '',
                status VARCHAR(24) NOT NULL DEFAULT 'ouvert',
                version_a_json MEDIUMTEXT NOT NULL,
                version_b_json MEDIUMTEXT NOT NULL,
                resolution_note TEXT NULL,
                resolved_by_label VARCHAR(160) DEFAULT NULL,
                resolved_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_sse_conflict_uuid (tenant_id, uuid),
                KEY idx_sse_conflict_status (tenant_id, status),
                CONSTRAINT fk_sse_conflict_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $log("  [OK] sse_sync_conflicts\n");
    } else {
        $log("  [OK] sse_sync_conflicts (déjà présente)\n");
    }

    if (!$tableExists($pdo, 'sse_job_locks')) {
        $pdo->exec(
            "CREATE TABLE sse_job_locks (
                lock_key VARCHAR(80) NOT NULL,
                tenant_id INT UNSIGNED NOT NULL DEFAULT 0,
                owner_label VARCHAR(120) NOT NULL DEFAULT '',
                locked_until DATETIME NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (lock_key, tenant_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $log("  [OK] sse_job_locks\n");
    } else {
        $log("  [OK] sse_job_locks (déjà présente)\n");
    }
};
