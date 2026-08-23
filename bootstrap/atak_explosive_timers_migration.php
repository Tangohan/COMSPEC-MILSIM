<?php

declare(strict_types=1);

/**
 * Charges à retardement (ACE minuterie) visibles sur l’ATAK web.
 * Idempotent — appelée depuis run-migrations.php.
 */
return static function (PDO $pdo): void {
    $tableExists = static function (PDO $pdo, string $table): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if ($tableExists($pdo, 'atak_explosive_timers')) {
        echo "  [OK] atak_explosive_timers (déjà présente)\n";

        return;
    }

    if (!$tableExists($pdo, 'tenants')) {
        echo "  [ATTENTION] tenants absente — charges à retardement reportées\n";

        return;
    }

    try {
        $pdo->exec(
            "CREATE TABLE atak_explosive_timers (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                map_id INT UNSIGNED NOT NULL DEFAULT 1,
                charge_id VARCHAR(96) NOT NULL,
                author VARCHAR(120) NOT NULL DEFAULT '',
                magazine_label VARCHAR(160) NOT NULL DEFAULT '',
                grid_ref VARCHAR(48) NOT NULL DEFAULT '',
                pos_x DOUBLE NOT NULL DEFAULT 0,
                pos_y DOUBLE NOT NULL DEFAULT 0,
                fuse_seconds INT UNSIGNED NOT NULL DEFAULT 0,
                trigger_kind VARCHAR(16) NOT NULL DEFAULT 'timer',
                status VARCHAR(16) NOT NULL DEFAULT 'armed',
                started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                detonates_at DATETIME DEFAULT NULL,
                ended_at DATETIME DEFAULT NULL,
                detonate_requested_at DATETIME DEFAULT NULL,
                detonate_requested_by VARCHAR(120) NOT NULL DEFAULT '',
                detonate_ack_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_explosive_timer_charge (tenant_id, map_id, charge_id),
                KEY idx_explosive_timer_tenant_map (tenant_id, map_id, status),
                KEY idx_explosive_timer_detonates (tenant_id, map_id, detonates_at),
                KEY idx_explosive_timer_pending_det (tenant_id, map_id, status, detonate_requested_at),
                CONSTRAINT fk_explosive_timer_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        echo "  [OK] atak_explosive_timers\n";
    } catch (Throwable $e) {
        echo '  [ATTENTION] atak_explosive_timers : ' . $e->getMessage() . "\n";
    }
};
