<?php

declare(strict_types=1);

/**
 * Crée le référentiel d'ancienneté configurable (définitions, périodes, règles).
 * Migration idempotente.
 */
function run_seniority_engine_migration(PDO $pdo): void
{
    $hasTable = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if (!$hasTable('seniority_definitions')) {
        $pdo->exec(
            "CREATE TABLE seniority_definitions (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                code VARCHAR(100) NOT NULL,
                label VARCHAR(150) NOT NULL,
                scope ENUM('user','tenant','unit','group','role','grade','qualification','mission','campaign','custom') NOT NULL,
                calc_mode ENUM('from_start','sum_periods','active_only','custom_rule') NOT NULL DEFAULT 'from_start',
                source_type VARCHAR(100) NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                is_visible TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_seniority_def_tenant_code (tenant_id, code),
                KEY idx_seniority_def_tenant_active (tenant_id, is_active, sort_order),
                CONSTRAINT seniority_definitions_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('seniority_periods')) {
        $pdo->exec(
            "CREATE TABLE seniority_periods (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                definition_id INT UNSIGNED NOT NULL,
                related_entity_type VARCHAR(100) DEFAULT NULL,
                related_entity_id INT UNSIGNED DEFAULT NULL,
                start_date DATE NOT NULL,
                end_date DATE DEFAULT NULL,
                status ENUM('active','inactive','suspended','cancelled') NOT NULL DEFAULT 'active',
                metadata JSON DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_seniority_periods_user_definition (user_id, definition_id),
                KEY idx_seniority_periods_tenant_user (tenant_id, user_id),
                KEY idx_seniority_periods_definition_dates (definition_id, start_date, end_date),
                CONSTRAINT seniority_periods_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT seniority_periods_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT seniority_periods_definition_fk FOREIGN KEY (definition_id) REFERENCES seniority_definitions (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('seniority_rules')) {
        $pdo->exec(
            "CREATE TABLE seniority_rules (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                definition_id INT UNSIGNED NOT NULL,
                rule_type ENUM('include_status','exclude_status','entity_filter','weight','minimum_duration','custom_php_key') NOT NULL,
                rule_value VARCHAR(255) NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_seniority_rules_definition (definition_id, sort_order),
                CONSTRAINT seniority_rules_definition_fk FOREIGN KEY (definition_id) REFERENCES seniority_definitions (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    echo "  [OK] seniority_engine (definitions, periods, rules)\n";
}
