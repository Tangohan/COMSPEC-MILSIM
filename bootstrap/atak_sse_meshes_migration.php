<?php

declare(strict_types=1);

/**
 * Toiles de données SSE (data mesh) — graphes d’enquête nommés.
 * Idempotent — appelée depuis run-migrations.php et ensureSchema().
 */
return static function (PDO $pdo, ?callable $log = null): void {
    $log ??= static function (string $msg): void {
        // Silence web.
    };

    $tableExists = static function (PDO $pdo, string $table): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if (!$tableExists($pdo, 'tenants')) {
        $log("  [ATTENTION] tenants absente — toiles SSE reportées\n");

        return;
    }

    if (!$tableExists($pdo, 'sse_meshes')) {
        $pdo->exec(
            "CREATE TABLE sse_meshes (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                reference_code VARCHAR(32) NOT NULL,
                title VARCHAR(200) NOT NULL DEFAULT '',
                summary TEXT NULL,
                case_id INT UNSIGNED DEFAULT NULL,
                classification VARCHAR(32) NOT NULL DEFAULT 'encadrement',
                status VARCHAR(32) NOT NULL DEFAULT 'ouvert',
                created_by INT UNSIGNED DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_sse_mesh_ref (tenant_id, reference_code),
                KEY idx_sse_meshes_tenant (tenant_id, status),
                KEY idx_sse_meshes_case (case_id),
                CONSTRAINT fk_sse_meshes_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $log("  [OK] sse_meshes\n");
    } else {
        $log("  [OK] sse_meshes (déjà présente)\n");
    }

    if (!$tableExists($pdo, 'sse_mesh_nodes')) {
        $pdo->exec(
            "CREATE TABLE sse_mesh_nodes (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                mesh_id INT UNSIGNED NOT NULL,
                tenant_id INT UNSIGNED NOT NULL,
                kind VARCHAR(32) NOT NULL DEFAULT 'custom',
                label VARCHAR(200) NOT NULL DEFAULT '',
                detail VARCHAR(255) DEFAULT NULL,
                ref_type VARCHAR(32) DEFAULT NULL,
                ref_id INT UNSIGNED DEFAULT NULL,
                pos_x DOUBLE NOT NULL DEFAULT 0,
                pos_y DOUBLE NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_sse_mesh_nodes_mesh (mesh_id),
                KEY idx_sse_mesh_nodes_ref (tenant_id, ref_type, ref_id),
                CONSTRAINT fk_sse_mesh_nodes_mesh FOREIGN KEY (mesh_id) REFERENCES sse_meshes (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_sse_mesh_nodes_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $log("  [OK] sse_mesh_nodes\n");
    } else {
        $log("  [OK] sse_mesh_nodes (déjà présente)\n");
    }

    $columnExists = static function (PDO $pdo, string $table, string $column): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    if ($tableExists($pdo, 'sse_mesh_nodes') && !$columnExists($pdo, 'sse_mesh_nodes', 'meta_json')) {
        $pdo->exec('ALTER TABLE sse_mesh_nodes ADD COLUMN meta_json TEXT NULL AFTER detail');
        $log("  [OK] sse_mesh_nodes.meta_json\n");
    }

    if (!$tableExists($pdo, 'sse_mesh_edges')) {
        $pdo->exec(
            "CREATE TABLE sse_mesh_edges (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                mesh_id INT UNSIGNED NOT NULL,
                tenant_id INT UNSIGNED NOT NULL,
                from_node_id INT UNSIGNED NOT NULL,
                to_node_id INT UNSIGNED NOT NULL,
                relation VARCHAR(48) NOT NULL DEFAULT 'associe',
                note VARCHAR(255) DEFAULT NULL,
                reliability VARCHAR(24) NOT NULL DEFAULT 'unverified',
                created_by INT UNSIGNED DEFAULT NULL,
                author_label VARCHAR(120) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_sse_mesh_edges_mesh (mesh_id),
                CONSTRAINT fk_sse_mesh_edges_mesh FOREIGN KEY (mesh_id) REFERENCES sse_meshes (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_sse_mesh_edges_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_sse_mesh_edges_from FOREIGN KEY (from_node_id) REFERENCES sse_mesh_nodes (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_sse_mesh_edges_to FOREIGN KEY (to_node_id) REFERENCES sse_mesh_nodes (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $log("  [OK] sse_mesh_edges\n");
    } else {
        $log("  [OK] sse_mesh_edges (déjà présente)\n");
    }
};
