<?php

declare(strict_types=1);

/**
 * DOMEX lot 1 — nœud d’objet + paquets « à exploiter ».
 * Idempotent — appelée depuis run-migrations.php et SseDigitalLabRepository::ensureSchema().
 */
return static function (PDO $pdo): void {
    $root = dirname(__DIR__);
    require_once $root . '/bootstrap/schema_ensure_column.php';

    if (schema_table_exists($pdo, 'sse_digital_devices')) {
        schema_ensure_columns($pdo, 'sse_digital_devices', [
            'node_key' => '`node_key` VARCHAR(40) DEFAULT NULL AFTER `arma_object_id`',
            'owner_label' => '`owner_label` VARCHAR(160) DEFAULT NULL AFTER `node_key`',
            'organization_label' => '`organization_label` VARCHAR(160) DEFAULT NULL AFTER `owner_label`',
            'fictional_network' => '`fictional_network` VARCHAR(80) DEFAULT NULL AFTER `organization_label`',
            'access_physical' => '`access_physical` TINYINT(1) NOT NULL DEFAULT 1 AFTER `fictional_network`',
            'access_remote' => '`access_remote` TINYINT(1) NOT NULL DEFAULT 0 AFTER `access_physical`',
            'security_tier' => '`security_tier` VARCHAR(24) DEFAULT NULL AFTER `access_remote`',
            'content_profile' => '`content_profile` VARCHAR(40) DEFAULT NULL AFTER `security_tier`',
            'terrain_stage' => '`terrain_stage` VARCHAR(32) DEFAULT NULL AFTER `content_profile`',
            'exploit_duration_s' => '`exploit_duration_s` SMALLINT UNSIGNED DEFAULT NULL AFTER `terrain_stage`',
        ]);
    }

    if (!schema_table_exists($pdo, 'sse_digital_packets')) {
        $pdo->exec("CREATE TABLE sse_digital_packets (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            mission_id INT UNSIGNED DEFAULT NULL,
            case_id INT UNSIGNED DEFAULT NULL,
            device_id INT UNSIGNED DEFAULT NULL,
            acquisition_id INT UNSIGNED DEFAULT NULL,
            node_key VARCHAR(40) NOT NULL,
            packet_uid VARCHAR(64) NOT NULL,
            packet_type VARCHAR(40) NOT NULL DEFAULT 'document',
            title VARCHAR(160) NOT NULL,
            body_text TEXT NOT NULL,
            occurred_at_label VARCHAR(40) DEFAULT NULL,
            quality VARCHAR(24) NOT NULL DEFAULT 'complet',
            is_decoy TINYINT(1) NOT NULL DEFAULT 0,
            is_fragment TINYINT(1) NOT NULL DEFAULT 0,
            is_complete TINYINT(1) NOT NULL DEFAULT 1,
            channel VARCHAR(24) NOT NULL DEFAULT 'physique',
            reveal_after VARCHAR(24) NOT NULL DEFAULT 'immediat',
            delay_seconds INT UNSIGNED NOT NULL DEFAULT 0,
            origin VARCHAR(24) NOT NULL DEFAULT 'terrain',
            confidence VARCHAR(24) NOT NULL DEFAULT 'non_evalue',
            status VARCHAR(24) NOT NULL DEFAULT 'a_exploiter',
            linked_entities_json MEDIUMTEXT NULL,
            collector_label VARCHAR(160) DEFAULT NULL,
            grid_reference VARCHAR(40) DEFAULT NULL,
            classification VARCHAR(40) NOT NULL DEFAULT 'confidentiel',
            compartment VARCHAR(80) DEFAULT NULL,
            created_by INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_sse_dig_pkt_uid (tenant_id, packet_uid),
            KEY idx_sse_dig_pkt_queue (tenant_id, status, updated_at),
            KEY idx_sse_dig_pkt_node (tenant_id, node_key),
            KEY idx_sse_dig_pkt_dev (tenant_id, device_id),
            CONSTRAINT fk_sse_dig_pkt_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    if (schema_table_exists($pdo, 'sse_digital_packets')) {
        schema_ensure_columns($pdo, 'sse_digital_packets', [
            'pos_x' => '`pos_x` DOUBLE DEFAULT NULL AFTER `grid_reference`',
            'pos_y' => '`pos_y` DOUBLE DEFAULT NULL AFTER `pos_x`',
            'pos_z' => '`pos_z` DOUBLE DEFAULT NULL AFTER `pos_y`',
            'show_on_map' => '`show_on_map` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pos_z`',
        ]);
    }
};
