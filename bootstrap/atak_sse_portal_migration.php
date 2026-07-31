<?php

declare(strict_types=1);

/**
 * Portail SSE privé — dossiers d’affaire, codes d’accès, notes, preuves, audit.
 * Idempotent — appelée depuis run-migrations.php.
 */
return static function (PDO $pdo): void {
    // Silencieux hors CLI : ensureSchema() des repos ne doit pas polluer le HTML (Tacmap).
    $log = static function (string $msg): void {
        if (PHP_SAPI === 'cli') {
            echo $msg;
        }
    };

    $tableExists = static function (PDO $pdo, string $table): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
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

    if (!$tableExists($pdo, 'tenants')) {
        $log("  [ATTENTION] tenants absente — portail SSE reporté\n");

        return;
    }

    // Assurer le schéma personnes de base
    $personsMigrate = require __DIR__ . '/atak_sse_persons_migration.php';
    if (is_callable($personsMigrate)) {
        $personsMigrate($pdo);
    }

    if (!$tableExists($pdo, 'sse_cases')) {
        $pdo->exec(
            "CREATE TABLE sse_cases (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                context_id INT UNSIGNED NOT NULL DEFAULT 1,
                reference_code VARCHAR(32) NOT NULL,
                title VARCHAR(200) NOT NULL DEFAULT '',
                summary TEXT NULL,
                classification VARCHAR(32) NOT NULL DEFAULT 'encadrement',
                status VARCHAR(32) NOT NULL DEFAULT 'ouvert',
                unlock_code_hash VARCHAR(64) DEFAULT NULL,
                created_by INT UNSIGNED DEFAULT NULL,
                closed_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_sse_case_ref (tenant_id, reference_code),
                KEY idx_sse_cases_tenant_status (tenant_id, status),
                KEY idx_sse_cases_class (tenant_id, classification),
                CONSTRAINT fk_sse_cases_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $log("  [OK] sse_cases\n");
    } else {
        $log("  [OK] sse_cases (déjà présente)\n");
    }

    if (!$tableExists($pdo, 'sse_case_persons') && $tableExists($pdo, 'sse_persons')) {
        $pdo->exec(
            "CREATE TABLE sse_case_persons (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                case_id INT UNSIGNED NOT NULL,
                person_id INT UNSIGNED NOT NULL,
                tenant_id INT UNSIGNED NOT NULL,
                linked_by INT UNSIGNED DEFAULT NULL,
                note VARCHAR(255) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_sse_case_person (case_id, person_id),
                KEY idx_sse_case_persons_tenant (tenant_id),
                CONSTRAINT fk_sse_case_persons_case FOREIGN KEY (case_id) REFERENCES sse_cases (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_sse_case_persons_person FOREIGN KEY (person_id) REFERENCES sse_persons (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_sse_case_persons_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $log("  [OK] sse_case_persons\n");
    } else {
        $log("  [OK] sse_case_persons (déjà présente ou sse_persons absente)\n");
    }

    if (!$tableExists($pdo, 'sse_case_notes')) {
        $pdo->exec(
            "CREATE TABLE sse_case_notes (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                case_id INT UNSIGNED NOT NULL,
                tenant_id INT UNSIGNED NOT NULL,
                body TEXT NOT NULL,
                classification VARCHAR(32) NOT NULL DEFAULT 'encadrement',
                author_user_id INT UNSIGNED DEFAULT NULL,
                author_label VARCHAR(120) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_sse_case_notes_case (case_id),
                CONSTRAINT fk_sse_case_notes_case FOREIGN KEY (case_id) REFERENCES sse_cases (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_sse_case_notes_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $log("  [OK] sse_case_notes\n");
    } else {
        $log("  [OK] sse_case_notes (déjà présente)\n");
    }

    if (!$tableExists($pdo, 'sse_case_evidence')) {
        $pdo->exec(
            "CREATE TABLE sse_case_evidence (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                case_id INT UNSIGNED NOT NULL,
                tenant_id INT UNSIGNED NOT NULL,
                label VARCHAR(200) NOT NULL DEFAULT '',
                caption TEXT NULL,
                image_path VARCHAR(512) DEFAULT NULL,
                person_id INT UNSIGNED DEFAULT NULL,
                author_label VARCHAR(120) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_sse_case_evidence_case (case_id),
                CONSTRAINT fk_sse_case_evidence_case FOREIGN KEY (case_id) REFERENCES sse_cases (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_sse_case_evidence_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $log("  [OK] sse_case_evidence\n");
    } else {
        $log("  [OK] sse_case_evidence (déjà présente)\n");
    }

    if (!$tableExists($pdo, 'sse_access_codes')) {
        $pdo->exec(
            "CREATE TABLE sse_access_codes (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                code_hash CHAR(64) NOT NULL,
                code_hint VARCHAR(16) NOT NULL DEFAULT '',
                label VARCHAR(160) NOT NULL DEFAULT '',
                grant_type VARCHAR(16) NOT NULL DEFAULT 'member',
                clearance_level VARCHAR(24) NOT NULL DEFAULT 'interne',
                case_id INT UNSIGNED DEFAULT NULL,
                created_by INT UNSIGNED DEFAULT NULL,
                expires_at DATETIME NOT NULL,
                session_ttl_minutes INT UNSIGNED NOT NULL DEFAULT 240,
                max_uses INT UNSIGNED NOT NULL DEFAULT 1,
                uses_count INT UNSIGNED NOT NULL DEFAULT 0,
                revoked_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_sse_access_hash (code_hash),
                KEY idx_sse_access_tenant (tenant_id, revoked_at),
                KEY idx_sse_access_expires (expires_at),
                CONSTRAINT fk_sse_access_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $log("  [OK] sse_access_codes\n");
    }

    // Habilitation portée par un code d'accès : sans elle, un allié invité reste
    // au plancher de lecture quel que soit ce qu'on voulait lui montrer.
    if ($tableExists($pdo, 'sse_access_codes') && !$columnExists($pdo, 'sse_access_codes', 'clearance_level')) {
        $pdo->exec("ALTER TABLE sse_access_codes ADD COLUMN clearance_level VARCHAR(24) NOT NULL DEFAULT 'interne' AFTER grant_type");
        $log("  [OK] sse_access_codes.clearance_level\n");
    }

    if (!$tableExists($pdo, 'sse_access_grants_log')) {
        $pdo->exec(
            "CREATE TABLE sse_access_grants_log (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                access_code_id INT UNSIGNED DEFAULT NULL,
                case_id INT UNSIGNED DEFAULT NULL,
                actor_user_id INT UNSIGNED DEFAULT NULL,
                actor_label VARCHAR(120) DEFAULT NULL,
                event_type VARCHAR(32) NOT NULL DEFAULT 'redeem',
                detail VARCHAR(255) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_sse_grants_tenant (tenant_id, created_at),
                CONSTRAINT fk_sse_grants_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $log("  [OK] sse_access_grants_log\n");
    } else {
        $log("  [OK] sse_access_grants_log (déjà présente)\n");
    }
};
