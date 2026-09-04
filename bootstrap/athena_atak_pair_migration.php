<?php

declare(strict_types=1);

/**
 * Appairage ATAK in-game : le téléphone génère un code court, l’opérateur le
 * saisit sur le portail (Carte → Compte → Lier le jeu), le jeu interroge
 * ensuite l’état jusqu’à validation.
 */
return static function (PDO $pdo): void {
    $tableExists = static function (PDO $pdo, string $table): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if ($tableExists($pdo, 'game_atak_pair_challenges')) {
        return;
    }

    try {
        $pdo->exec(
            "CREATE TABLE game_atak_pair_challenges (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                device_code_hash CHAR(64) NOT NULL,
                user_code_hash CHAR(64) NOT NULL,
                steam_id VARCHAR(32) DEFAULT NULL,
                terminal_uid VARCHAR(64) DEFAULT NULL,
                device_id VARCHAR(64) DEFAULT NULL,
                mod_version VARCHAR(32) DEFAULT NULL,
                account_id BIGINT UNSIGNED DEFAULT NULL,
                user_id INT UNSIGNED DEFAULT NULL,
                tenant_id INT UNSIGNED DEFAULT NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'pending',
                expires_at DATETIME NOT NULL,
                approved_at DATETIME DEFAULT NULL,
                consumed_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_gapc_device (device_code_hash),
                KEY idx_gapc_user_code (user_code_hash, status),
                KEY idx_gapc_status_exp (status, expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        echo "  [OK] game_atak_pair_challenges\n";
    } catch (Throwable $e) {
        echo '  [ATTENTION] game_atak_pair_challenges : ' . $e->getMessage() . "\n";
    }
};
