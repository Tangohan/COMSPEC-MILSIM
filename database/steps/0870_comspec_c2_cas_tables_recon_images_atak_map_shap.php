<?php

declare(strict_types=1);

/**
 * Étape « COMSPEC C2/CAS : tables recon_images, atak_map_shapes, atak_laser_codes » (Schéma).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// COMSPEC C2/CAS : tables recon_images, atak_map_shapes, atak_laser_codes
$c2Tables = [
    'recon_images' => "CREATE TABLE recon_images (
        id int unsigned NOT NULL AUTO_INCREMENT,
        tenant_id int unsigned NOT NULL,
        mission_id varchar(128) DEFAULT NULL,
        author_callsign varchar(128) NOT NULL,
        unit_name varchar(255) DEFAULT NULL,
        side varchar(16) DEFAULT 'WEST',
        image_path varchar(500) NOT NULL,
        thumb_path varchar(500) DEFAULT NULL,
        caption text DEFAULT NULL,
        pos_x decimal(15,4) DEFAULT NULL,
        pos_y decimal(15,4) DEFAULT NULL,
        pos_z decimal(15,4) DEFAULT NULL,
        grid_ref varchar(32) DEFAULT NULL,
        heading decimal(8,2) DEFAULT NULL,
        altitude decimal(10,2) DEFAULT NULL,
        device_type varchar(64) DEFAULT 'CTAB',
        captured_at datetime DEFAULT NULL,
        atak_cas_id int unsigned DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY tenant_mission (tenant_id, mission_id),
        KEY author_callsign (author_callsign),
        KEY captured_at (captured_at),
        CONSTRAINT recon_images_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    'atak_map_shapes' => "CREATE TABLE atak_map_shapes (
        id int unsigned NOT NULL AUTO_INCREMENT,
        tenant_id int unsigned NOT NULL,
        map_id int unsigned NOT NULL DEFAULT 1,
        mission_id varchar(128) DEFAULT NULL,
        shape_uid varchar(64) NOT NULL,
        type varchar(32) NOT NULL,
        label varchar(255) DEFAULT NULL,
        color varchar(32) DEFAULT '#3388ff',
        stroke int unsigned DEFAULT 2,
        fill_opacity decimal(3,2) DEFAULT 0.15,
        created_by varchar(128) DEFAULT NULL,
        visible_to json DEFAULT NULL,
        geometry json NOT NULL,
        meta json DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY tenant_map_uid (tenant_id, map_id, shape_uid),
        KEY tenant_map (tenant_id, map_id),
        CONSTRAINT atak_map_shapes_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    'atak_laser_codes' => "CREATE TABLE atak_laser_codes (
        id int unsigned NOT NULL AUTO_INCREMENT,
        tenant_id int unsigned NOT NULL,
        map_id int unsigned NOT NULL DEFAULT 1,
        call_sign varchar(128) NOT NULL,
        laser_code varchar(32) NOT NULL,
        pos_x decimal(15,4) DEFAULT NULL,
        pos_y decimal(15,4) DEFAULT NULL,
        status varchar(32) DEFAULT 'ACTIVE',
        last_update bigint DEFAULT NULL,
        updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY tenant_map_callsign (tenant_id, map_id, call_sign),
        KEY tenant_map (tenant_id, map_id),
        CONSTRAINT atak_laser_codes_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];
foreach ($c2Tables as $table => $createSql) {
    $stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table'");
    if ($stmt && !$stmt->fetch()) {
        echo "Création table $table...\n";
        $pdo->exec($createSql);
        echo "$table OK.\n";
    }
}
