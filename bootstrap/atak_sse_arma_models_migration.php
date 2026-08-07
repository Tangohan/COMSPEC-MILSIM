<?php

declare(strict_types=1);

/**
 * SSE — Modèles Arma (atelier de préparation terrain).
 * Idempotent — appelée depuis run-migrations.php et ensureSchema().
 */
return static function (PDO $pdo): void {
    $tableExists = static function (string $name) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$name]);

        return (bool) $st->fetchColumn();
    };

    if (!$tableExists('sse_arma_models')) {
        $pdo->exec("CREATE TABLE sse_arma_models (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            public_id VARCHAR(80) NOT NULL,
            name VARCHAR(160) NOT NULL,
            author_label VARCHAR(120) DEFAULT NULL,
            source VARCHAR(20) NOT NULL DEFAULT 'WEB',
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            profile_code VARCHAR(40) NOT NULL DEFAULT 'INSURGENT',
            complexity_code VARCHAR(40) NOT NULL DEFAULT 'DETAILED',
            region_code VARCHAR(40) NOT NULL DEFAULT 'IRAQ',
            theme_code VARCHAR(40) NOT NULL DEFAULT 'weapons_cache',
            include_biometrics TINYINT(1) NOT NULL DEFAULT 1,
            include_phone TINYINT(1) NOT NULL DEFAULT 1,
            include_documents TINYINT(1) NOT NULL DEFAULT 1,
            include_computer TINYINT(1) NOT NULL DEFAULT 0,
            network_size SMALLINT UNSIGNED NOT NULL DEFAULT 8,
            noise_probability DECIMAL(4,3) DEFAULT NULL,
            false_lead_probability DECIMAL(4,3) DEFAULT NULL,
            notes TEXT NULL,
            payload_json LONGTEXT NOT NULL,
            tags_json TEXT NULL,
            version INT UNSIGNED NOT NULL DEFAULT 1,
            created_by INT UNSIGNED DEFAULT NULL,
            updated_by INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_sse_arma_model_pub (tenant_id, public_id),
            KEY idx_sse_arma_model_list (tenant_id, status, updated_at),
            KEY idx_sse_arma_model_profile (tenant_id, profile_code),
            CONSTRAINT fk_sse_arma_model_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
};
