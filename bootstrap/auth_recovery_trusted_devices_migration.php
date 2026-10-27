<?php

declare(strict_types=1);

/**
 * Double vérification : codes de secours (usage unique, stockés hachés) et appareils de confiance
 * (« Se souvenir de cet appareil » : le code n'est plus demandé pendant 30 jours sur ce navigateur).
 *
 * Idempotent — appelée depuis run-migrations.php.
 */
return static function (PDO $pdo): void {
    $hasTable = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if (!$hasTable('user_recovery_codes')) {
        $pdo->exec(
            "CREATE TABLE user_recovery_codes (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT UNSIGNED NOT NULL,
                code_hash CHAR(64) NOT NULL,
                used_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_user_recovery_codes_user (user_id),
                UNIQUE KEY uq_user_recovery_codes_hash (user_id, code_hash)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('user_trusted_devices')) {
        $pdo->exec(
            "CREATE TABLE user_trusted_devices (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT UNSIGNED NOT NULL,
                token_hash CHAR(64) NOT NULL,
                password_fingerprint CHAR(64) NOT NULL,
                label VARCHAR(160) NOT NULL DEFAULT '',
                ip VARCHAR(45) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_used_at DATETIME DEFAULT NULL,
                expires_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_user_trusted_devices_token (token_hash),
                KEY idx_user_trusted_devices_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
};
