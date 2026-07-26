<?php

declare(strict_types=1);

/**
 * Passerelles ATAK inter-communautés (code unique + validation des deux côtés).
 * Idempotent.
 */
function run_atak_map_gateway_migration(PDO $pdo): void
{
    $hasTable = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if (!$hasTable('atak_map_gateways')) {
        $pdo->exec(
            "CREATE TABLE atak_map_gateways (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                host_tenant_id INT UNSIGNED NOT NULL,
                partner_tenant_id INT UNSIGNED NULL,
                join_code VARCHAR(12) NOT NULL,
                status VARCHAR(32) NOT NULL DEFAULT 'open',
                label VARCHAR(160) NULL,
                share_units TINYINT(1) NOT NULL DEFAULT 1,
                share_markers TINYINT(1) NOT NULL DEFAULT 0,
                host_map_id INT UNSIGNED NOT NULL DEFAULT 1,
                partner_map_id INT UNSIGNED NULL,
                created_by_user_id INT UNSIGNED NULL,
                expires_at DATETIME NOT NULL,
                activated_at DATETIME NULL,
                revoked_at DATETIME NULL,
                revoke_reason VARCHAR(255) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_atak_map_gateway_code (join_code),
                KEY idx_atak_map_gateway_host (host_tenant_id, status),
                KEY idx_atak_map_gateway_partner (partner_tenant_id, status),
                KEY idx_atak_map_gateway_expires (expires_at),
                CONSTRAINT fk_atak_map_gateway_host FOREIGN KEY (host_tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_atak_map_gateway_partner FOREIGN KEY (partner_tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('atak_map_gateway_acceptances')) {
        $pdo->exec(
            "CREATE TABLE atak_map_gateway_acceptances (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                gateway_id BIGINT UNSIGNED NOT NULL,
                tenant_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                accepted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_atak_map_gateway_acceptance (gateway_id, tenant_id),
                KEY idx_atak_map_gateway_acceptance_tenant (tenant_id),
                CONSTRAINT fk_atak_map_gateway_acc_gw FOREIGN KEY (gateway_id) REFERENCES atak_map_gateways (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_atak_map_gateway_acc_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_atak_map_gateway_acc_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
}
