<?php

declare(strict_types=1);

/**
 * LOT 6 — Analyse SSE :
 * Pattern of Life, heatmap, contradictions, rapprochements, anomalies explicables.
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
        $log("  [SKIP] tenants absente — LOT 6 différé\n");

        return;
    }

    if (!$tableExists($pdo, 'sse_analysis_findings')) {
        $pdo->exec(
            "CREATE TABLE sse_analysis_findings (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                uuid CHAR(36) NOT NULL,
                tenant_id INT UNSIGNED NOT NULL,
                context_id INT UNSIGNED NOT NULL DEFAULT 1,
                case_id INT UNSIGNED DEFAULT NULL,
                entity_uuid CHAR(36) DEFAULT NULL,
                finding_type VARCHAR(24) NOT NULL DEFAULT 'anomaly',
                severity VARCHAR(16) NOT NULL DEFAULT 'normale',
                status VARCHAR(24) NOT NULL DEFAULT 'ouvert',
                confidence_label VARCHAR(16) NOT NULL DEFAULT 'PROBABLE',
                title VARCHAR(220) NOT NULL DEFAULT '',
                explanation TEXT NOT NULL,
                evidence_json MEDIUMTEXT NULL,
                decided_by_label VARCHAR(160) DEFAULT NULL,
                decided_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_sse_find_uuid (tenant_id, uuid),
                KEY idx_sse_find_case (tenant_id, case_id, status),
                KEY idx_sse_find_type (tenant_id, finding_type, status),
                KEY idx_sse_find_entity (tenant_id, entity_uuid),
                CONSTRAINT fk_sse_find_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $log("  [OK] sse_analysis_findings\n");
    } else {
        $log("  [OK] sse_analysis_findings (déjà présente)\n");
    }

    if (!$tableExists($pdo, 'sse_pol_snapshots')) {
        $pdo->exec(
            "CREATE TABLE sse_pol_snapshots (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                case_id INT UNSIGNED DEFAULT NULL,
                entity_uuid CHAR(36) DEFAULT NULL,
                window_days SMALLINT UNSIGNED NOT NULL DEFAULT 14,
                profile_json MEDIUMTEXT NOT NULL,
                computed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_sse_pol_case (tenant_id, case_id, computed_at),
                KEY idx_sse_pol_entity (tenant_id, entity_uuid, computed_at),
                CONSTRAINT fk_sse_pol_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $log("  [OK] sse_pol_snapshots\n");
    } else {
        $log("  [OK] sse_pol_snapshots (déjà présente)\n");
    }
};
