<?php

declare(strict_types=1);

/**
 * Identité Athena globale (accounts) + appartenances tenant + sessions jeu Overwatch.
 */
return static function (PDO $pdo, ?callable $log = null): void {
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

    if (!$tableExists('athena_accounts')) {
        $pdo->exec(
            "CREATE TABLE athena_accounts (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_id VARCHAR(40) NOT NULL,
                email VARCHAR(190) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                email_verified_at DATETIME DEFAULT NULL,
                steam_id VARCHAR(32) DEFAULT NULL,
                status VARCHAR(32) NOT NULL DEFAULT 'active',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_athena_accounts_public (public_id),
                UNIQUE KEY uk_athena_accounts_email (email),
                UNIQUE KEY uk_athena_accounts_steam (steam_id),
                KEY idx_athena_accounts_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $say('athena_accounts created');
    }

    if (!$tableExists('account_tenant_memberships')) {
        $pdo->exec(
            "CREATE TABLE account_tenant_memberships (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                account_id BIGINT UNSIGNED NOT NULL,
                tenant_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                is_default TINYINT(1) NOT NULL DEFAULT 0,
                last_used_at DATETIME DEFAULT NULL,
                status VARCHAR(32) NOT NULL DEFAULT 'active',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_atm_account_tenant (account_id, tenant_id),
                UNIQUE KEY uk_atm_user (user_id),
                KEY idx_atm_account_default (account_id, is_default),
                KEY idx_atm_tenant (tenant_id),
                CONSTRAINT fk_atm_account FOREIGN KEY (account_id) REFERENCES athena_accounts (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_atm_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_atm_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $say('account_tenant_memberships created');
    }

    if (!$tableExists('game_sessions')) {
        $pdo->exec(
            "CREATE TABLE game_sessions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_id VARCHAR(40) NOT NULL,
                account_id BIGINT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                tenant_id INT UNSIGNED NOT NULL,
                device_id VARCHAR(64) NOT NULL,
                access_token_hash CHAR(64) NOT NULL,
                refresh_token_hash CHAR(64) NOT NULL,
                steam_id VARCHAR(32) DEFAULT NULL,
                pairing_token_hash CHAR(64) DEFAULT NULL,
                mod_version VARCHAR(32) DEFAULT NULL,
                extension_version VARCHAR(32) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_seen_at DATETIME DEFAULT NULL,
                expires_at DATETIME NOT NULL,
                refresh_expires_at DATETIME NOT NULL,
                revoked_at DATETIME DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uk_game_sessions_public (public_id),
                UNIQUE KEY uk_game_sessions_access (access_token_hash),
                UNIQUE KEY uk_game_sessions_refresh (refresh_token_hash),
                KEY idx_game_sessions_device (device_id, account_id),
                KEY idx_game_sessions_account (account_id, expires_at),
                CONSTRAINT fk_gs_account FOREIGN KEY (account_id) REFERENCES athena_accounts (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_gs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_gs_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $say('game_sessions created');
    }

    if (!$tableExists('game_auth_otps')) {
        $pdo->exec(
            "CREATE TABLE game_auth_otps (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                email VARCHAR(190) NOT NULL,
                code_hash CHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                consumed_at DATETIME DEFAULT NULL,
                attempts INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_game_otp_email (email, expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $say('game_auth_otps created');
    }

    if (!$tableExists('game_device_pairings')) {
        $pdo->exec(
            "CREATE TABLE game_device_pairings (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                account_id BIGINT UNSIGNED NOT NULL,
                device_id VARCHAR(64) NOT NULL,
                steam_id VARCHAR(32) NOT NULL,
                pairing_token_hash CHAR(64) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_used_at DATETIME DEFAULT NULL,
                revoked_at DATETIME DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uk_gdp_account_device (account_id, device_id),
                KEY idx_gdp_steam_device (steam_id, device_id),
                CONSTRAINT fk_gdp_account FOREIGN KEY (account_id) REFERENCES athena_accounts (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $say('game_device_pairings created');
    }

    if ($tableExists('tenant_atak_config') && !$columnExists('tenant_atak_config', 'overwatch_game_experience')) {
        $pdo->exec(
            'ALTER TABLE tenant_atak_config ADD COLUMN overwatch_game_experience JSON DEFAULT NULL'
        );
        $say('tenant_atak_config.overwatch_game_experience added');
    }

    // ATHENA a pu conserver en base un minimum plus récent que le pack publié.
    // Ne toucher à aucun autre réglage de l'expérience Overwatch du tenant.
    if ($tableExists('tenants') && $tableExists('tenant_atak_config') && $columnExists('tenant_atak_config', 'overwatch_game_experience')) {
        $athenaConfigs = $pdo->query(
            "SELECT tac.tenant_id, tac.overwatch_game_experience
             FROM tenant_atak_config tac
             INNER JOIN tenants t ON t.id = tac.tenant_id
             WHERE LOWER(TRIM(t.slug)) = 'athena' OR UPPER(TRIM(t.name)) = 'ATHENA'"
        );
        if ($athenaConfigs !== false) {
            $rows = $athenaConfigs->fetchAll(PDO::FETCH_ASSOC);
            $athenaConfigs->closeCursor();
            $updateAthenaConfig = $pdo->prepare(
                'UPDATE tenant_atak_config SET overwatch_game_experience = ?, updated_at = NOW() WHERE tenant_id = ?'
            );
            foreach ($rows as $row) {
                $raw = (string) ($row['overwatch_game_experience'] ?? '');
                $config = json_decode($raw, true);
                if (!is_array($config)) {
                    continue;
                }
                $normalized = \App\Support\OverwatchMinimumVersionOverride::lowerIfAbove($config, '1.5.0');
                if ($normalized === $config) {
                    continue;
                }
                $json = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($json !== false) {
                    $updateAthenaConfig->execute([$json, (int) $row['tenant_id']]);
                    $say('ATHENA overwatch.min_mod_version lowered to 1.5.0');
                }
            }
        }
    }

    if (!$tableExists('athena_accounts') || !$tableExists('users')) {
        return;
    }

    $count = (int) $pdo->query('SELECT COUNT(*) FROM athena_accounts')->fetchColumn();
    if ($count > 0) {
        return;
    }

    $users = $pdo->query(
        "SELECT id, tenant_id, email, password_hash, status, steam_id, email_verified_at, updated_at, created_at
         FROM users
         WHERE email IS NOT NULL AND TRIM(email) <> ''
         ORDER BY updated_at DESC, id DESC"
    );
    if ($users === false) {
        return;
    }

    $byEmail = [];
    while ($row = $users->fetch(PDO::FETCH_ASSOC)) {
        $email = strtolower(trim((string) ($row['email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            continue;
        }
        $byEmail[$email][] = $row;
    }

    $insAccount = $pdo->prepare(
        'INSERT INTO athena_accounts (public_id, email, password_hash, email_verified_at, steam_id, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $insMem = $pdo->prepare(
        'INSERT IGNORE INTO account_tenant_memberships (account_id, tenant_id, user_id, is_default, last_used_at, status)
         VALUES (?, ?, ?, ?, ?, ?)'
    );

    $created = 0;
    foreach ($byEmail as $email => $rows) {
        $primary = $rows[0];
        $hash = (string) ($primary['password_hash'] ?? '');
        if ($hash === '') {
            continue;
        }
        $steam = null;
        $verified = null;
        $status = 'disabled';
        $createdAt = (string) ($primary['created_at'] ?? date('Y-m-d H:i:s'));
        foreach ($rows as $row) {
            if ($steam === null) {
                $sid = trim((string) ($row['steam_id'] ?? ''));
                if ($sid !== '') {
                    $steam = $sid;
                }
            }
            if ($verified === null && !empty($row['email_verified_at'])) {
                $verified = $row['email_verified_at'];
            }
            if ((string) ($row['status'] ?? '') === 'active') {
                $status = 'active';
            }
        }
        $publicId = 'acc_' . bin2hex(random_bytes(12));
        try {
            $insAccount->execute([$publicId, $email, $hash, $verified, $steam, $status, $createdAt]);
        } catch (Throwable) {
            continue;
        }
        $accountId = (int) $pdo->lastInsertId();
        if ($accountId < 1) {
            continue;
        }
        $defaultSet = false;
        foreach ($rows as $row) {
            $uid = (int) ($row['id'] ?? 0);
            $tid = (int) ($row['tenant_id'] ?? 0);
            if ($uid < 1 || $tid < 1) {
                continue;
            }
            $isDefault = $defaultSet ? 0 : 1;
            $memStatus = (string) ($row['status'] ?? 'active') === 'active' ? 'active' : 'disabled';
            $insMem->execute([
                $accountId,
                $tid,
                $uid,
                $isDefault,
                $row['updated_at'] ?? null,
                $memStatus,
            ]);
            $defaultSet = true;
        }
        $created++;
    }
    $say('athena_accounts backfill: ' . $created . ' identities');
};
