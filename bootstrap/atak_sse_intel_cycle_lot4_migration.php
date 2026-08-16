<?php

declare(strict_types=1);

/**
 * LOT 4 — Cycle de renseignement SSE :
 * exigences (PIR / SIR / EEI), taskings, produits (validation / sanitisation / diffusion).
 */
return static function (PDO $pdo, ?callable $log = null): void {
    $log ??= static function (string $m): void {
        echo $m;
    };

    $tableExists = static function (PDO $pdo, string $table): bool {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table]);

        return (bool) $stmt->fetchColumn();
    };

    $columnExists = static function (PDO $pdo, string $table, string $column): bool {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table, $column]);

        return (bool) $stmt->fetchColumn();
    };

    if (!$tableExists($pdo, 'tenants')) {
        $log("  [SKIP] tenants absente — LOT 4 différé\n");

        return;
    }

    // ---- Exigences de collecte (PIR / SIR / EEI) ----
    if (!$tableExists($pdo, 'sse_intel_requirements')) {
        $pdo->exec(
            "CREATE TABLE sse_intel_requirements (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                uuid CHAR(36) NOT NULL,
                tenant_id INT UNSIGNED NOT NULL,
                context_id INT UNSIGNED NOT NULL DEFAULT 1,
                case_id INT UNSIGNED DEFAULT NULL,
                req_type VARCHAR(8) NOT NULL DEFAULT 'PIR',
                reference_code VARCHAR(48) DEFAULT NULL,
                title VARCHAR(220) NOT NULL DEFAULT '',
                question TEXT NOT NULL,
                priority VARCHAR(16) NOT NULL DEFAULT 'normale',
                status VARCHAR(24) NOT NULL DEFAULT 'ouvert',
                coverage_pct TINYINT UNSIGNED NOT NULL DEFAULT 0,
                linked_hypothesis VARCHAR(8) DEFAULT NULL,
                confirmation_criterion TEXT NULL,
                assignee_label VARCHAR(160) DEFAULT NULL,
                due_at DATE DEFAULT NULL,
                satisfied_at DATETIME DEFAULT NULL,
                author_label VARCHAR(160) DEFAULT NULL,
                created_by INT UNSIGNED DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_sse_req_uuid (tenant_id, uuid),
                KEY idx_sse_req_case (tenant_id, case_id, status),
                KEY idx_sse_req_type (tenant_id, req_type, status),
                KEY idx_sse_req_prio (tenant_id, priority, status),
                CONSTRAINT fk_sse_req_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $log("  [OK] sse_intel_requirements\n");
    } else {
        $log("  [OK] sse_intel_requirements (déjà présente)\n");
    }

    // ---- Taskings (ordres de collecte) ----
    if (!$tableExists($pdo, 'sse_intel_taskings')) {
        $pdo->exec(
            "CREATE TABLE sse_intel_taskings (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                uuid CHAR(36) NOT NULL,
                tenant_id INT UNSIGNED NOT NULL,
                requirement_id INT UNSIGNED NOT NULL,
                case_id INT UNSIGNED DEFAULT NULL,
                title VARCHAR(220) NOT NULL DEFAULT '',
                instruction TEXT NOT NULL,
                tasked_unit VARCHAR(160) DEFAULT NULL,
                tasked_callsign VARCHAR(80) DEFAULT NULL,
                priority VARCHAR(16) NOT NULL DEFAULT 'normale',
                status VARCHAR(24) NOT NULL DEFAULT 'brouillon',
                due_at DATETIME DEFAULT NULL,
                completed_at DATETIME DEFAULT NULL,
                result_summary TEXT NULL,
                author_label VARCHAR(160) DEFAULT NULL,
                created_by INT UNSIGNED DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_sse_task_uuid (tenant_id, uuid),
                KEY idx_sse_task_req (tenant_id, requirement_id, status),
                KEY idx_sse_task_case (tenant_id, case_id, status),
                CONSTRAINT fk_sse_task_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_sse_task_req FOREIGN KEY (requirement_id) REFERENCES sse_intel_requirements (id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $log("  [OK] sse_intel_taskings\n");
    } else {
        $log("  [OK] sse_intel_taskings (déjà présente)\n");
    }

    // ---- Produits de renseignement (rapports cycle) ----
    if (!$tableExists($pdo, 'sse_intel_products')) {
        $pdo->exec(
            "CREATE TABLE sse_intel_products (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                uuid CHAR(36) NOT NULL,
                tenant_id INT UNSIGNED NOT NULL,
                case_id INT UNSIGNED NOT NULL,
                requirement_id INT UNSIGNED DEFAULT NULL,
                product_type VARCHAR(24) NOT NULL DEFAULT 'INITIAL',
                title VARCHAR(220) NOT NULL DEFAULT '',
                body_text MEDIUMTEXT NOT NULL,
                classification VARCHAR(32) NOT NULL DEFAULT 'encadrement',
                release_level VARCHAR(32) NOT NULL DEFAULT 'interne',
                status VARCHAR(24) NOT NULL DEFAULT 'brouillon',
                sanitised TINYINT(1) NOT NULL DEFAULT 0,
                sanitised_at DATETIME DEFAULT NULL,
                validated_at DATETIME DEFAULT NULL,
                validated_by_label VARCHAR(160) DEFAULT NULL,
                diffused_at DATETIME DEFAULT NULL,
                author_label VARCHAR(160) DEFAULT NULL,
                created_by INT UNSIGNED DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_sse_prod_uuid (tenant_id, uuid),
                KEY idx_sse_prod_case (tenant_id, case_id, status),
                KEY idx_sse_prod_status (tenant_id, status),
                CONSTRAINT fk_sse_prod_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $log("  [OK] sse_intel_products\n");
    } else {
        $log("  [OK] sse_intel_products (déjà présente)\n");
    }

    // ---- Destinataires de diffusion ----
    if (!$tableExists($pdo, 'sse_intel_product_recipients')) {
        $pdo->exec(
            "CREATE TABLE sse_intel_product_recipients (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                product_id INT UNSIGNED NOT NULL,
                recipient_label VARCHAR(160) NOT NULL,
                recipient_role VARCHAR(80) DEFAULT NULL,
                ack_status VARCHAR(24) NOT NULL DEFAULT 'envoye',
                ack_at DATETIME DEFAULT NULL,
                note VARCHAR(255) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_sse_recip_prod (tenant_id, product_id),
                CONSTRAINT fk_sse_recip_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_sse_recip_prod FOREIGN KEY (product_id) REFERENCES sse_intel_products (id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $log("  [OK] sse_intel_product_recipients\n");
    } else {
        $log("  [OK] sse_intel_product_recipients (déjà présente)\n");
    }

    // Lien optionnel lacunes analytiques → exigence
    if ($tableExists($pdo, 'sse_case_intel_gaps')
        && !$columnExists($pdo, 'sse_case_intel_gaps', 'requirement_id')
    ) {
        $pdo->exec(
            'ALTER TABLE sse_case_intel_gaps
             ADD COLUMN requirement_id INT UNSIGNED DEFAULT NULL AFTER case_id'
        );
        $log("  [OK] sse_case_intel_gaps.requirement_id\n");
    }
};
