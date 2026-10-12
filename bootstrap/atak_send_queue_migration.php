<?php

declare(strict_types=1);

/**
 * Files d'envoi COMSPEC Link (POST /api/atak/send-queue/report, AtakSendQueueRepository).
 *
 * - atak_send_queue_snapshots : dernier bilan de chaque joueur ou serveur (une ligne par communauté,
 *   Steam ID et type de client), avec les envois suivis (items_json) et leurs étapes ;
 * - atak_send_queue_history : un résumé par bilan reçu, gardé 48 h (état d'Athena dans le temps).
 *
 * Idempotent.
 */
return static function (PDO $pdo): void {
    $tableExists = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if (!$tableExists('atak_send_queue_snapshots')) {
        $pdo->exec(
            "CREATE TABLE atak_send_queue_snapshots (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                steam_uid VARCHAR(32) NOT NULL,
                client_kind VARCHAR(8) NOT NULL DEFAULT 'client',
                call_sign VARCHAR(64) DEFAULT NULL,
                extension_version VARCHAR(32) DEFAULT NULL,
                mod_version VARCHAR(32) DEFAULT NULL,
                queued INT UNSIGNED NOT NULL DEFAULT 0,
                sending INT UNSIGNED NOT NULL DEFAULT 0,
                retry INT UNSIGNED NOT NULL DEFAULT 0,
                failed INT UNSIGNED NOT NULL DEFAULT 0,
                done INT UNSIGNED NOT NULL DEFAULT 0,
                oldest_queued_s INT UNSIGNED NOT NULL DEFAULT 0,
                overload VARCHAR(16) NOT NULL DEFAULT 'ok',
                rate_limited_for_s INT UNSIGNED NOT NULL DEFAULT 0,
                backoff_for_s INT UNSIGNED NOT NULL DEFAULT 0,
                avg_latency_ms INT UNSIGNED NOT NULL DEFAULT 0,
                p95_latency_ms INT UNSIGNED NOT NULL DEFAULT 0,
                http_429 INT UNSIGNED NOT NULL DEFAULT 0,
                http_5xx INT UNSIGNED NOT NULL DEFAULT 0,
                timeouts INT UNSIGNED NOT NULL DEFAULT 0,
                window_s INT UNSIGNED NOT NULL DEFAULT 0,
                items_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                items_json MEDIUMTEXT DEFAULT NULL,
                received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_atak_sq_snap_client (tenant_id, steam_uid, client_kind),
                KEY idx_atak_sq_snap_recent (tenant_id, received_at),
                CONSTRAINT fk_atak_sq_snap_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        echo "  [OK] atak_send_queue_snapshots\n";
    }

    if (!$tableExists('atak_send_queue_history')) {
        $pdo->exec(
            "CREATE TABLE atak_send_queue_history (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                steam_uid VARCHAR(32) NOT NULL,
                client_kind VARCHAR(8) NOT NULL DEFAULT 'client',
                overload VARCHAR(16) NOT NULL DEFAULT 'ok',
                queued INT UNSIGNED NOT NULL DEFAULT 0,
                retry INT UNSIGNED NOT NULL DEFAULT 0,
                failed INT UNSIGNED NOT NULL DEFAULT 0,
                avg_latency_ms INT UNSIGNED NOT NULL DEFAULT 0,
                http_429 INT UNSIGNED NOT NULL DEFAULT 0,
                http_5xx INT UNSIGNED NOT NULL DEFAULT 0,
                timeouts INT UNSIGNED NOT NULL DEFAULT 0,
                received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_atak_sq_hist_tenant (tenant_id, received_at),
                KEY idx_atak_sq_hist_received (received_at),
                CONSTRAINT fk_atak_sq_hist_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        echo "  [OK] atak_send_queue_history\n";
    }
};
