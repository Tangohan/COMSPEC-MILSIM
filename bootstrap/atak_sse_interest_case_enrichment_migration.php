<?php

declare(strict_types=1);

/**
 * Enrichissement des dossiers d’intérêt : description, journal horodaté,
 * destinataires / interdits nominatifs, horodatages de délai d’action.
 *
 * Valeurs NULL / tables vides = comportement historique inchangé
 * (visibilité large SSE, pas de destinataires imposés).
 */
return static function (PDO $pdo): void {
    $tableExists = static function (PDO $pdo, string $table): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    $columnExists = static function (PDO $pdo, string $table, string $column): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    if (!$tableExists($pdo, 'sse_interest_cases')) {
        return;
    }

    if (!$columnExists($pdo, 'sse_interest_cases', 'description')) {
        $pdo->exec(
            'ALTER TABLE sse_interest_cases
             ADD COLUMN description MEDIUMTEXT NULL AFTER opening_reason'
        );
    }

    if (!$tableExists($pdo, 'sse_interest_case_acl')) {
        $pdo->exec("CREATE TABLE sse_interest_case_acl (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            interest_case_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            access_mode VARCHAR(16) NOT NULL,
            created_by INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_sse_interest_acl (interest_case_id, user_id, access_mode),
            KEY idx_sse_interest_acl_case (tenant_id, interest_case_id, access_mode),
            KEY idx_sse_interest_acl_user (tenant_id, user_id, access_mode),
            CONSTRAINT fk_sse_interest_acl_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_sse_interest_acl_case FOREIGN KEY (interest_case_id) REFERENCES sse_interest_cases (id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_sse_interest_acl_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    if (!$tableExists($pdo, 'sse_interest_case_updates')) {
        $pdo->exec("CREATE TABLE sse_interest_case_updates (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            interest_case_id INT UNSIGNED NOT NULL,
            body TEXT NOT NULL,
            author_label VARCHAR(160) DEFAULT NULL,
            author_user_id INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_sse_interest_updates_case (tenant_id, interest_case_id, created_at),
            CONSTRAINT fk_sse_interest_upd_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_sse_interest_upd_case FOREIGN KEY (interest_case_id) REFERENCES sse_interest_cases (id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    if (!$tableExists($pdo, 'sse_interest_case_cooldowns')) {
        $pdo->exec("CREATE TABLE sse_interest_case_cooldowns (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            interest_case_id INT UNSIGNED NOT NULL,
            action_key VARCHAR(40) NOT NULL,
            last_at DATETIME NOT NULL,
            last_by INT UNSIGNED DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_sse_interest_cd (interest_case_id, action_key),
            KEY idx_sse_interest_cd_case (tenant_id, interest_case_id),
            CONSTRAINT fk_sse_interest_cd_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_sse_interest_cd_case FOREIGN KEY (interest_case_id) REFERENCES sse_interest_cases (id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    // Signature numérique du signalement (optionnelle sur historiques non signés).
    if (!$columnExists($pdo, 'sse_interest_cases', 'signed_by_label')) {
        $pdo->exec(
            'ALTER TABLE sse_interest_cases
             ADD COLUMN signed_by_label VARCHAR(160) NULL AFTER source_reliability'
        );
    }
    if (!$columnExists($pdo, 'sse_interest_cases', 'signed_at')) {
        $pdo->exec(
            'ALTER TABLE sse_interest_cases
             ADD COLUMN signed_at DATETIME NULL AFTER signed_by_label'
        );
    }
};
