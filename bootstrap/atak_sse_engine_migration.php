<?php

declare(strict_types=1);

/**
 * Moteur analytique SSE — file de suggestions + signaux.
 *
 * Principe : le moteur propose (possible / probable), jamais de fusion ni
 * de relation « confirmée » sans validation humaine.
 */
return static function (PDO $pdo): void {
    $tableExists = static function (PDO $pdo, string $table): bool {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
        $stmt->execute([$table]);

        return (bool) $stmt->fetchColumn();
    };

    if (!$tableExists($pdo, 'sse_suggestion_queue')) {
        $pdo->exec("CREATE TABLE sse_suggestion_queue (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            case_id INT UNSIGNED DEFAULT NULL,
            related_case_id INT UNSIGNED DEFAULT NULL,
            left_type VARCHAR(24) NOT NULL,
            left_id INT UNSIGNED NOT NULL,
            right_type VARCHAR(24) NOT NULL,
            right_id INT UNSIGNED NOT NULL,
            kind VARCHAR(40) NOT NULL,
            score TINYINT UNSIGNED NOT NULL DEFAULT 0,
            confidence VARCHAR(16) NOT NULL DEFAULT 'possible',
            status VARCHAR(16) NOT NULL DEFAULT 'pending',
            title VARCHAR(220) NOT NULL DEFAULT '',
            reason VARCHAR(500) NOT NULL DEFAULT '',
            evidence_json MEDIUMTEXT NULL,
            rule_key VARCHAR(64) NOT NULL DEFAULT '',
            run_id VARCHAR(40) DEFAULT NULL,
            decided_by INT UNSIGNED DEFAULT NULL,
            decided_at DATETIME DEFAULT NULL,
            author_label VARCHAR(160) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_sse_sugg (tenant_id, kind, left_type, left_id, right_type, right_id),
            KEY idx_sse_sugg_case (tenant_id, case_id, status),
            KEY idx_sse_sugg_status (tenant_id, status, confidence),
            KEY idx_sse_sugg_run (tenant_id, run_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    if (!$tableExists($pdo, 'sse_engine_signals')) {
        $pdo->exec("CREATE TABLE sse_engine_signals (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            case_id INT UNSIGNED DEFAULT NULL,
            signal_type VARCHAR(40) NOT NULL,
            severity VARCHAR(16) NOT NULL DEFAULT 'info',
            title VARCHAR(220) NOT NULL,
            detail MEDIUMTEXT NULL,
            payload_json MEDIUMTEXT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'open',
            rule_key VARCHAR(64) NOT NULL DEFAULT '',
            run_id VARCHAR(40) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_sse_signal_case (tenant_id, case_id, status),
            KEY idx_sse_signal_type (tenant_id, signal_type, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    if (!$tableExists($pdo, 'sse_case_completeness')) {
        $pdo->exec("CREATE TABLE sse_case_completeness (
            case_id INT UNSIGNED NOT NULL,
            tenant_id INT UNSIGNED NOT NULL,
            score TINYINT UNSIGNED NOT NULL DEFAULT 0,
            breakdown_json MEDIUMTEXT NULL,
            digest_json MEDIUMTEXT NULL,
            computed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (case_id),
            KEY idx_sse_comp_tenant (tenant_id, score)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
};
