<?php

declare(strict_types=1);

/**
 * Codes d’invitation prioritaires (candidature / enrôlement).
 * Tables recruitment_invite_codes + recruitment_invite_code_uses,
 * colonne enlistments.invite_code_id, et dissociation code_kind = priority.
 * Idempotent — appelée depuis run-migrations.php.
 */
return function (PDO $pdo): void {
    $tableExists = static function (PDO $pdo, string $table): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    $hasCol = static function (PDO $pdo, string $table, string $column): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    $hasIndex = static function (PDO $pdo, string $table, string $index): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $index]);

        return (bool) $st->fetchColumn();
    };

    if (!$tableExists($pdo, 'recruitment_invite_codes')) {
        $pdo->exec(
            "CREATE TABLE recruitment_invite_codes (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT UNSIGNED NOT NULL,
                code VARCHAR(64) NOT NULL COMMENT 'Code unique utilisable par les candidats',
                label VARCHAR(255) DEFAULT NULL COMMENT 'Libellé interne pour identifier ce code',
                code_kind VARCHAR(32) NOT NULL DEFAULT 'priority' COMMENT 'priority = accélération candidature',
                max_uses INT UNSIGNED DEFAULT NULL COMMENT 'Nombre maximum d utilisations (NULL = illimité)',
                uses_count INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Nombre d utilisations effectuées',
                expires_at DATETIME DEFAULT NULL COMMENT 'Date d expiration du code (NULL = pas d expiration)',
                auto_accept TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Valide automatiquement la candidature',
                assign_to_opening_id INT UNSIGNED DEFAULT NULL COMMENT 'Offre de recrutement liée automatiquement',
                default_specialty VARCHAR(255) DEFAULT NULL COMMENT 'Spécialité par défaut à affecter',
                metadata_json TEXT DEFAULT NULL COMMENT 'Métadonnées additionnelles',
                created_by INT UNSIGNED DEFAULT NULL COMMENT 'Créateur du code',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_tenant_code (tenant_id, code),
                INDEX idx_tenant_expires (tenant_id, expires_at),
                INDEX idx_code_lookup (code),
                INDEX idx_tenant_kind (tenant_id, code_kind),
                CONSTRAINT fk_invite_codes_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        if (PHP_SAPI === 'cli') {
            echo "  [OK] recruitment_invite_codes créée\n";
        }
    }

    if ($tableExists($pdo, 'recruitment_invite_codes') && !$hasCol($pdo, 'recruitment_invite_codes', 'code_kind')) {
        $pdo->exec(
            "ALTER TABLE recruitment_invite_codes
             ADD COLUMN code_kind VARCHAR(32) NOT NULL DEFAULT 'priority'
             COMMENT 'priority = accélération candidature'
             AFTER label"
        );
        if (PHP_SAPI === 'cli') {
            echo "  [OK] recruitment_invite_codes.code_kind ajoutée\n";
        }
    }

    if ($tableExists($pdo, 'recruitment_invite_codes') && !$hasIndex($pdo, 'recruitment_invite_codes', 'idx_tenant_kind')) {
        try {
            $pdo->exec('ALTER TABLE recruitment_invite_codes ADD INDEX idx_tenant_kind (tenant_id, code_kind)');
        } catch (Throwable $e) {
            if (PHP_SAPI === 'cli') {
                echo '  [ATTENTION] idx_tenant_kind : ' . $e->getMessage() . "\n";
            }
        }
    }

    if (!$tableExists($pdo, 'recruitment_invite_code_uses')) {
        $pdo->exec(
            "CREATE TABLE recruitment_invite_code_uses (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT UNSIGNED NOT NULL,
                invite_code_id INT UNSIGNED NOT NULL,
                enlistment_id INT UNSIGNED NOT NULL COMMENT 'Candidature créée avec ce code',
                code_used VARCHAR(64) NOT NULL COMMENT 'Valeur du code au moment de l utilisation',
                used_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_tenant_code (tenant_id, invite_code_id),
                INDEX idx_enlistment (enlistment_id),
                CONSTRAINT fk_code_uses_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
                CONSTRAINT fk_code_uses_invite FOREIGN KEY (invite_code_id) REFERENCES recruitment_invite_codes (id) ON DELETE CASCADE,
                CONSTRAINT fk_code_uses_enlistment FOREIGN KEY (enlistment_id) REFERENCES enlistments (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        if (PHP_SAPI === 'cli') {
            echo "  [OK] recruitment_invite_code_uses créée\n";
        }
    }

    if ($tableExists($pdo, 'enlistments') && !$hasCol($pdo, 'enlistments', 'invite_code_id')) {
        try {
            $pdo->exec(
                'ALTER TABLE enlistments
                 ADD COLUMN invite_code_id INT UNSIGNED DEFAULT NULL
                 COMMENT \'Code d invitation prioritaire utilisé\'
                 AFTER recruitment_opening_id'
            );
            if (!$hasIndex($pdo, 'enlistments', 'idx_invite_code')) {
                $pdo->exec('ALTER TABLE enlistments ADD INDEX idx_invite_code (invite_code_id)');
            }
            if (PHP_SAPI === 'cli') {
                echo "  [OK] enlistments.invite_code_id ajoutée\n";
            }
        } catch (Throwable $e) {
            if (PHP_SAPI === 'cli') {
                echo '  [ATTENTION] enlistments.invite_code_id : ' . $e->getMessage() . "\n";
            }
        }
    }
};
