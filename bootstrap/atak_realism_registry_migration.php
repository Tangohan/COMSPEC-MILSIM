<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $tableExists = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    $hasColumn = static function (string $table, string $column) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    if (!$tableExists('atak_terminals')) {
        $pdo->exec(
            'CREATE TABLE atak_terminals (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED DEFAULT NULL,
                terminal_uid VARCHAR(64) NOT NULL,
                terminal_label VARCHAR(160) NOT NULL,
                terminal_type VARCHAR(40) NOT NULL DEFAULT \'phone\',
                platform_label VARCHAR(120) DEFAULT NULL,
                operator_callsign VARCHAR(120) DEFAULT NULL,
                operator_military_id VARCHAR(32) DEFAULT NULL,
                pairing_token CHAR(32) DEFAULT NULL,
                pairing_code VARCHAR(8) DEFAULT NULL,
                status VARCHAR(32) NOT NULL DEFAULT \'pending\',
                compromise_state VARCHAR(32) NOT NULL DEFAULT \'none\',
                compromised_at DATETIME DEFAULT NULL,
                compromise_reason VARCHAR(255) DEFAULT NULL,
                first_seen_at DATETIME DEFAULT NULL,
                last_seen_at DATETIME DEFAULT NULL,
                linked_at DATETIME DEFAULT NULL,
                notes TEXT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_atak_terminals_tenant_uid (tenant_id, terminal_uid),
                KEY idx_atak_terminals_tenant_status (tenant_id, status),
                KEY idx_atak_terminals_user (user_id),
                CONSTRAINT fk_atak_terminals_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_atak_terminals_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    } else {
        if (!$hasColumn('atak_terminals', 'compromise_state')) {
            $pdo->exec(
                "ALTER TABLE atak_terminals
                 ADD COLUMN compromise_state VARCHAR(32) NOT NULL DEFAULT 'none' AFTER status"
            );
        }
        if (!$hasColumn('atak_terminals', 'compromised_at')) {
            $pdo->exec(
                'ALTER TABLE atak_terminals
                 ADD COLUMN compromised_at DATETIME DEFAULT NULL AFTER compromise_state'
            );
        }
        if (!$hasColumn('atak_terminals', 'compromise_reason')) {
            $pdo->exec(
                'ALTER TABLE atak_terminals
                 ADD COLUMN compromise_reason VARCHAR(255) DEFAULT NULL AFTER compromised_at'
            );
        }
    }

    if (!$tableExists('atak_crypto_domains')) {
        $pdo->exec(
            'CREATE TABLE atak_crypto_domains (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                map_id INT UNSIGNED DEFAULT NULL,
                domain_ref VARCHAR(64) NOT NULL,
                label VARCHAR(160) NOT NULL,
                faction_key VARCHAR(64) DEFAULT NULL,
                status VARCHAR(32) NOT NULL DEFAULT \'active\',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_atak_crypto_domains_ref (tenant_id, domain_ref),
                KEY idx_atak_crypto_domains_tenant_status (tenant_id, status),
                CONSTRAINT fk_atak_crypto_domains_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    if (!$tableExists('atak_certificates')) {
        $pdo->exec(
            'CREATE TABLE atak_certificates (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                terminal_id INT UNSIGNED DEFAULT NULL,
                user_id INT UNSIGNED DEFAULT NULL,
                crypto_domain_id INT UNSIGNED DEFAULT NULL,
                certificate_ref VARCHAR(64) NOT NULL,
                authority_label VARCHAR(160) NOT NULL,
                certificate_type VARCHAR(40) NOT NULL DEFAULT \'device\',
                common_name VARCHAR(255) DEFAULT NULL,
                serial_number VARCHAR(120) DEFAULT NULL,
                fingerprint_sha256 VARCHAR(128) DEFAULT NULL,
                status VARCHAR(32) NOT NULL DEFAULT \'issued\',
                issued_at DATETIME DEFAULT NULL,
                valid_from DATETIME DEFAULT NULL,
                expires_at DATETIME DEFAULT NULL,
                revoked_at DATETIME DEFAULT NULL,
                revoked_reason VARCHAR(255) DEFAULT NULL,
                metadata_json JSON DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_atak_certificates_ref (tenant_id, certificate_ref),
                KEY idx_atak_certificates_status (tenant_id, status),
                KEY idx_atak_certificates_terminal (terminal_id),
                KEY idx_atak_certificates_user (user_id),
                KEY idx_atak_certificates_domain (crypto_domain_id),
                CONSTRAINT fk_atak_certificates_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_atak_certificates_terminal FOREIGN KEY (terminal_id) REFERENCES atak_terminals (id) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT fk_atak_certificates_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT fk_atak_certificates_domain FOREIGN KEY (crypto_domain_id) REFERENCES atak_crypto_domains (id) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    } else {
        if (!$hasColumn('atak_certificates', 'crypto_domain_id')) {
            $pdo->exec(
                'ALTER TABLE atak_certificates
                 ADD COLUMN crypto_domain_id INT UNSIGNED DEFAULT NULL AFTER user_id'
            );
            try {
                $pdo->exec(
                    'ALTER TABLE atak_certificates
                     ADD KEY idx_atak_certificates_domain (crypto_domain_id)'
                );
            } catch (Throwable) {
            }
            try {
                $pdo->exec(
                    'ALTER TABLE atak_certificates
                     ADD CONSTRAINT fk_atak_certificates_domain
                     FOREIGN KEY (crypto_domain_id) REFERENCES atak_crypto_domains (id)
                     ON DELETE SET NULL ON UPDATE CASCADE'
                );
            } catch (Throwable) {
            }
        }
    }
};
