<?php

declare(strict_types=1);

/**
 * Étape « Tables ATAK live (parité Node, full PHP) » (Schéma).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Tables ATAK live (parité Node, full PHP)
$atakLiveTables = [
    'atak_layers' => "CREATE TABLE atak_layers (
        id int unsigned NOT NULL AUTO_INCREMENT,
        tenant_id int unsigned NOT NULL,
        map_id int unsigned NOT NULL DEFAULT 1,
        label varchar(255) NOT NULL,
        phase int DEFAULT NULL, `order` int NOT NULL DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id), KEY tenant_map (tenant_id, map_id),
        CONSTRAINT atak_layers_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    'atak_markers' => "CREATE TABLE atak_markers (
        id int unsigned NOT NULL AUTO_INCREMENT,
        tenant_id int unsigned NOT NULL,
        map_id int unsigned NOT NULL DEFAULT 1,
        layer_id int unsigned NOT NULL DEFAULT 1,
        marker_data text NOT NULL,
        arma_name varchar(255) DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id), KEY tenant_map (tenant_id, map_id),
        CONSTRAINT atak_markers_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    'atak_units' => "CREATE TABLE atak_units (
        id int unsigned NOT NULL AUTO_INCREMENT,
        tenant_id int unsigned NOT NULL,
        map_id int unsigned NOT NULL DEFAULT 1,
        call_sign varchar(255) NOT NULL,
        military_id varchar(32) DEFAULT NULL,
        role varchar(255) DEFAULT NULL,
        status varchar(50) DEFAULT 'linked',
        grid_ref varchar(100) DEFAULT NULL,
        heading decimal(10,4) DEFAULT NULL,
        pos_x decimal(15,4) DEFAULT NULL,
        pos_y decimal(15,4) DEFAULT NULL,
        extra json DEFAULT NULL,
        updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id), KEY tenant_map (tenant_id, map_id), KEY map_callsign (map_id, call_sign),
        KEY idx_atak_units_tenant_military (tenant_id, military_id),
        CONSTRAINT atak_units_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    'atak_chat_messages' => "CREATE TABLE atak_chat_messages (
        id int unsigned NOT NULL AUTO_INCREMENT,
        tenant_id int unsigned NOT NULL,
        map_id int unsigned NOT NULL DEFAULT 1,
        author varchar(255) NOT NULL,
        body text NOT NULL,
        source varchar(16) NOT NULL DEFAULT 'game',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id), KEY tenant_map (tenant_id, map_id),
        CONSTRAINT atak_chat_messages_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    'atak_pings' => "CREATE TABLE atak_pings (
        id int unsigned NOT NULL AUTO_INCREMENT,
        tenant_id int unsigned NOT NULL,
        map_id int unsigned NOT NULL DEFAULT 1,
        author varchar(255) NOT NULL,
        pos_x decimal(15,4) NOT NULL,
        pos_y decimal(15,4) NOT NULL,
        message text DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id), KEY tenant_map (tenant_id, map_id),
        CONSTRAINT atak_pings_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    'atak_nine_line' => "CREATE TABLE atak_nine_line (
        id int unsigned NOT NULL AUTO_INCREMENT,
        tenant_id int unsigned NOT NULL,
        map_id int unsigned NOT NULL DEFAULT 1,
        author varchar(255) NOT NULL,
        line1 varchar(255) DEFAULT NULL, line2 varchar(255) DEFAULT NULL, line3 varchar(255) DEFAULT NULL,
        line4 varchar(255) DEFAULT NULL, line5 varchar(255) DEFAULT NULL, line6 varchar(255) DEFAULT NULL,
        line7 varchar(255) DEFAULT NULL, line8 varchar(255) DEFAULT NULL, line9 text DEFAULT NULL,
        status varchar(50) DEFAULT 'active',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id), KEY tenant_map (tenant_id, map_id),
        CONSTRAINT atak_nine_line_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    'atak_intel_photos' => "CREATE TABLE atak_intel_photos (
        id int unsigned NOT NULL AUTO_INCREMENT,
        tenant_id int unsigned NOT NULL,
        map_id int unsigned NOT NULL DEFAULT 1,
        filename varchar(255) NOT NULL,
        path varchar(500) NOT NULL,
        author varchar(255) DEFAULT NULL,
        pos_x decimal(15,4) DEFAULT NULL,
        pos_y decimal(15,4) DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id), KEY tenant_map (tenant_id, map_id),
        CONSTRAINT atak_intel_photos_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    'atak_designator_targets' => "CREATE TABLE atak_designator_targets (
        id int unsigned NOT NULL AUTO_INCREMENT,
        tenant_id int unsigned NOT NULL,
        map_id int unsigned NOT NULL DEFAULT 1,
        call_sign varchar(255) NOT NULL,
        pos_x decimal(15,4) NOT NULL,
        pos_y decimal(15,4) NOT NULL,
        updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY tenant_map_callsign (tenant_id, map_id, call_sign),
        CONSTRAINT atak_designator_targets_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    'atak_sigint_reports' => "CREATE TABLE atak_sigint_reports (
        id int unsigned NOT NULL AUTO_INCREMENT,
        tenant_id int unsigned NOT NULL,
        map_id int unsigned NOT NULL DEFAULT 1,
        call_sign varchar(255) NOT NULL,
        pos_x decimal(15,4) NOT NULL,
        pos_y decimal(15,4) NOT NULL,
        bearing decimal(10,4) DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id), KEY tenant_map (tenant_id, map_id),
        CONSTRAINT atak_sigint_reports_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    'atak_rf_hits' => "CREATE TABLE atak_rf_hits (
        id bigint unsigned NOT NULL AUTO_INCREMENT,
        tenant_id int unsigned NOT NULL,
        map_id int unsigned NOT NULL DEFAULT 1,
        emitter_uid varchar(96) NOT NULL,
        label varchar(255) NOT NULL DEFAULT '',
        band varchar(32) NOT NULL DEFAULT 'unknown',
        signature_id varchar(64) NOT NULL DEFAULT '',
        pos_x double NOT NULL DEFAULT 0,
        pos_y double NOT NULL DEFAULT 0,
        signal_dbm double DEFAULT NULL,
        sensor_callsign varchar(128) NOT NULL DEFAULT '',
        payload json DEFAULT NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_atak_rf_hits_map (tenant_id, map_id, created_at),
        KEY idx_atak_rf_hits_emitter (tenant_id, map_id, emitter_uid),
        CONSTRAINT atak_rf_hits_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    'atak_last_activity' => "CREATE TABLE atak_last_activity (
        tenant_id int unsigned NOT NULL,
        map_id int unsigned NOT NULL DEFAULT 1,
        last_activity_at datetime NOT NULL,
        PRIMARY KEY (tenant_id, map_id),
        CONSTRAINT atak_last_activity_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    'atak_air_assets' => "CREATE TABLE atak_air_assets (
        id int unsigned NOT NULL AUTO_INCREMENT,
        tenant_id int unsigned NOT NULL,
        map_id int unsigned NOT NULL DEFAULT 1,
        callsign varchar(128) NOT NULL,
        model varchar(255) DEFAULT NULL,
        aircraft_type varchar(32) DEFAULT NULL,
        freq varchar(64) DEFAULT NULL,
        laser varchar(32) DEFAULT '1688',
        auth varchar(128) DEFAULT NULL,
        pos_x decimal(15,4) DEFAULT NULL,
        pos_y decimal(15,4) DEFAULT NULL,
        alt decimal(10,2) DEFAULT NULL,
        heading decimal(8,2) DEFAULT NULL,
        side varchar(16) DEFAULT 'WEST',
        status varchar(32) DEFAULT 'IN-FLIGHT',
        pilot_status varchar(32) DEFAULT NULL,
        aircraft_count int unsigned DEFAULT 1,
        updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY tenant_map_callsign (tenant_id, map_id, callsign),
        KEY tenant_map (tenant_id, map_id),
        CONSTRAINT atak_air_assets_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];
foreach ($atakLiveTables as $table => $createSql) {
    $stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table'");
    if ($stmt && !$stmt->fetch()) {
        echo "Création table $table...\n";
        $pdo->exec($createSql);
        echo "$table OK.\n";
    }
}
