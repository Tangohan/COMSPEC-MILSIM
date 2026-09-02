<?php

declare(strict_types=1);

/**
 * Un compte plateforme, plusieurs communautés.
 * Schéma idempotent : appartenances, fiches communauté, journal de fusion,
 * dossiers RH scopés (user_id, tenant_id). La fusion des doublons e-mail
 * n’est pas exécutée ici — voir UserIdentityMergeService.
 *
 * function_exists : le fichier est chargé par run-migrations.php (require_once)
 * et peut l’être à nouveau dans la même requête par SilentSchemaMigration (annuaire,
 * appartenances, fusion). Sans garde, PHP s’arrête (Cannot redeclare).
 */
if (!function_exists('run_user_community_identity_migration')) {
function run_user_community_identity_migration(PDO $pdo, ?callable $log = null): void
{
    $say = static function (string $message) use ($log): void {
        if ($log !== null) {
            $log($message);
        }
    };

    $tableExists = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    $columnExists = static function (string $table, string $column) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    $indexExists = static function (string $table, string $index) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $index]);

        return (bool) $st->fetchColumn();
    };

    if (!$tableExists('users') || !$tableExists('tenants')) {
        $say('user_community_identity deferred (users/tenants absents)');

        return;
    }

    if (!$tableExists('user_community_memberships')) {
        $pdo->exec(
            "CREATE TABLE user_community_memberships (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT UNSIGNED NOT NULL,
                tenant_id INT UNSIGNED NOT NULL,
                status VARCHAR(32) NOT NULL DEFAULT 'active',
                source_user_id INT UNSIGNED DEFAULT NULL COMMENT 'Ancien users.id avant fusion (réversible)',
                joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                left_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_ucm_user_tenant (user_id, tenant_id),
                KEY idx_ucm_tenant_status (tenant_id, status),
                KEY idx_ucm_source (source_user_id),
                CONSTRAINT fk_ucm_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_ucm_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $say('user_community_memberships created');
    }

    if (!$tableExists('user_community_profiles')) {
        $pdo->exec(
            "CREATE TABLE user_community_profiles (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT UNSIGNED NOT NULL,
                tenant_id INT UNSIGNED NOT NULL,
                display_name VARCHAR(100) DEFAULT NULL,
                callsign VARCHAR(50) DEFAULT NULL,
                profile_slug VARCHAR(40) DEFAULT NULL,
                athena_identifier CHAR(9) DEFAULT NULL,
                role_id INT UNSIGNED DEFAULT NULL,
                grade_id INT UNSIGNED DEFAULT NULL,
                status VARCHAR(50) DEFAULT 'pending',
                tenant_member_number VARCHAR(40) DEFAULT NULL,
                nationality_code VARCHAR(8) DEFAULT NULL,
                preferred_grade_format VARCHAR(20) DEFAULT NULL,
                professional_category_code VARCHAR(40) DEFAULT NULL,
                preferred_display_role_id INT UNSIGNED DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_ucp_user_tenant (user_id, tenant_id),
                UNIQUE KEY uk_ucp_tenant_slug (tenant_id, profile_slug),
                UNIQUE KEY uk_ucp_athena (athena_identifier),
                KEY idx_ucp_tenant_status (tenant_id, status),
                CONSTRAINT fk_ucp_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_ucp_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $say('user_community_profiles created');
    }

    if (!$tableExists('user_identity_merges')) {
        $pdo->exec(
            "CREATE TABLE user_identity_merges (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                survivor_user_id INT UNSIGNED NOT NULL,
                absorbed_user_id INT UNSIGNED NOT NULL,
                email VARCHAR(255) NOT NULL,
                absorbed_tenant_id INT UNSIGNED NOT NULL,
                steam_collision TINYINT(1) NOT NULL DEFAULT 0,
                absorbed_steam_id VARCHAR(32) DEFAULT NULL,
                absorbed_snapshot LONGTEXT DEFAULT NULL,
                notes VARCHAR(500) DEFAULT NULL,
                merged_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_uim_absorbed (absorbed_user_id),
                KEY idx_uim_survivor (survivor_user_id),
                KEY idx_uim_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $say('user_identity_merges created');
    }

    foreach (['personnel_profiles', 'personnel_extras'] as $rhTable) {
        if (!$tableExists($rhTable)) {
            continue;
        }
        if (!$columnExists($rhTable, 'tenant_id')) {
            $pdo->exec("ALTER TABLE `{$rhTable}` ADD COLUMN tenant_id INT UNSIGNED DEFAULT NULL");
            $pdo->exec(
                "UPDATE `{$rhTable}` rh
                 INNER JOIN users u ON u.id = rh.user_id
                 SET rh.tenant_id = u.tenant_id
                 WHERE rh.tenant_id IS NULL"
            );
            $say("{$rhTable}.tenant_id added and backfilled");
        }
        $oldUnique = $rhTable === 'personnel_profiles' ? 'personnel_profiles_user_id' : 'PRIMARY';
        if ($rhTable === 'personnel_profiles' && $indexExists($rhTable, 'personnel_profiles_user_id')) {
            try {
                $pdo->exec('ALTER TABLE personnel_profiles DROP INDEX personnel_profiles_user_id');
            } catch (Throwable) {
            }
        }
        if ($rhTable === 'personnel_extras' && $indexExists($rhTable, 'PRIMARY')) {
            $pkCols = [];
            $st = $pdo->query(
                "SELECT COLUMN_NAME FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_extras' AND INDEX_NAME = 'PRIMARY'
                 ORDER BY SEQ_IN_INDEX"
            );
            if ($st) {
                $pkCols = array_column($st->fetchAll(PDO::FETCH_ASSOC) ?: [], 'COLUMN_NAME');
            }
            if ($pkCols === ['user_id']) {
                try {
                    $pdo->exec('ALTER TABLE personnel_extras DROP PRIMARY KEY');
                    $pdo->exec(
                        'ALTER TABLE personnel_extras ADD COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (id)'
                    );
                } catch (Throwable $e) {
                    $say('personnel_extras pk rotate skipped: ' . $e->getMessage());
                }
            }
        }
        $ukName = $rhTable === 'personnel_profiles' ? 'uk_pp_user_tenant' : 'uk_pe_user_tenant';
        if (!$indexExists($rhTable, $ukName)) {
            try {
                $pdo->exec("ALTER TABLE `{$rhTable}` ADD UNIQUE KEY `{$ukName}` (user_id, tenant_id)");
                $say("{$rhTable} unique (user_id, tenant_id)");
            } catch (Throwable $e) {
                $say("{$rhTable} unique skipped: " . $e->getMessage());
            }
        }
        if (!$indexExists($rhTable, 'idx_' . substr($rhTable, 0, 12) . '_tenant')) {
            try {
                $pdo->exec("ALTER TABLE `{$rhTable}` ADD KEY `idx_" . substr($rhTable, 0, 12) . "_tenant` (tenant_id)");
            } catch (Throwable) {
            }
        }
    }

    if ($tableExists('account_tenant_memberships') && $indexExists('account_tenant_memberships', 'uk_atm_user')) {
        try {
            $pdo->exec('ALTER TABLE account_tenant_memberships DROP INDEX uk_atm_user');
            $say('account_tenant_memberships.uk_atm_user dropped (un user, plusieurs communautés)');
        } catch (Throwable $e) {
            $say('uk_atm_user drop skipped: ' . $e->getMessage());
        }
    }

    if ($tableExists('users') && !$columnExists('users', 'email_identity')) {
        try {
            $pdo->exec(
                "ALTER TABLE users
                 ADD COLUMN email_identity VARCHAR(280)
                 GENERATED ALWAYS AS (
                    IF(IFNULL(is_service_account, 0) = 1,
                       CONCAT(LOWER(TRIM(email)), '#', tenant_id),
                       LOWER(TRIM(email))
                    )
                 ) STORED"
            );
            $say('users.email_identity generated');
        } catch (Throwable $e) {
            $say('email_identity skipped: ' . $e->getMessage());
        }
    }

    if ($tableExists('users') && $columnExists('users', 'email_identity') && !$indexExists('users', 'uk_users_email_identity')) {
        try {
            $pdo->exec('ALTER TABLE users ADD UNIQUE KEY uk_users_email_identity (email_identity)');
            $say('uk_users_email_identity added');
        } catch (Throwable $e) {
            $say('uk_users_email_identity deferred (doublons encore présents) : ' . $e->getMessage());
        }
    }

    if ($tableExists('users') && $tableExists('user_community_memberships')) {
        $pdo->exec(
            "INSERT IGNORE INTO user_community_memberships (user_id, tenant_id, status, source_user_id, joined_at)
             SELECT u.id, u.tenant_id,
                    CASE WHEN u.status IN ('inactive', 'deleted') THEN 'left' ELSE 'active' END,
                    u.id,
                    COALESCE(u.created_at, NOW())
             FROM users u
             WHERE u.tenant_id IS NOT NULL AND u.tenant_id > 0"
        );
    }

    if ($tableExists('users') && $tableExists('user_community_profiles')) {
        $hasTmn = $columnExists('users', 'tenant_member_number');
        $hasSlug = $columnExists('users', 'profile_slug');
        $hasAthena = $columnExists('users', 'athena_identifier');
        $hasNat = $columnExists('users', 'nationality_code');
        $hasPref = $columnExists('users', 'preferred_grade_format');
        $hasProf = $columnExists('users', 'professional_category_code');
        $hasDisp = $columnExists('users', 'preferred_display_role_id');
        $cols = 'user_id, tenant_id, display_name, callsign, role_id, grade_id, status';
        $sel = 'u.id, u.tenant_id, u.display_name, u.callsign, u.role_id, u.grade_id, u.status';
        if ($hasSlug) {
            $cols .= ', profile_slug';
            $sel .= ', u.profile_slug';
        }
        if ($hasAthena) {
            $cols .= ', athena_identifier';
            $sel .= ', u.athena_identifier';
        }
        if ($hasTmn) {
            $cols .= ', tenant_member_number';
            $sel .= ', u.tenant_member_number';
        }
        if ($hasNat) {
            $cols .= ', nationality_code';
            $sel .= ', u.nationality_code';
        }
        if ($hasPref) {
            $cols .= ', preferred_grade_format';
            $sel .= ', u.preferred_grade_format';
        }
        if ($hasProf) {
            $cols .= ', professional_category_code';
            $sel .= ', u.professional_category_code';
        }
        if ($hasDisp) {
            $cols .= ', preferred_display_role_id';
            $sel .= ', u.preferred_display_role_id';
        }
        $pdo->exec(
            "INSERT IGNORE INTO user_community_profiles ({$cols}, created_at)
             SELECT {$sel}, COALESCE(u.created_at, NOW())
             FROM users u
             WHERE u.tenant_id IS NOT NULL AND u.tenant_id > 0"
        );
    }
}
}

return static function (PDO $pdo, ?callable $log = null): void {
    run_user_community_identity_migration($pdo, $log);
};
