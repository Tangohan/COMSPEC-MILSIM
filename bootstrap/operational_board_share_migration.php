<?php

declare(strict_types=1);

/**
 * Lien public lecture seule du mur opérationnel (jeton opaque par communauté).
 * Idempotent — appelée depuis run-migrations.php.
 */
return function (PDO $pdo): void {
    $chk = $pdo->query(
        "SELECT 1 FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'operational_board_shares' LIMIT 1"
    );
    if ($chk && $chk->fetch()) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE operational_board_shares (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            share_token CHAR(64) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_obs_token (share_token),
            UNIQUE KEY uq_obs_tenant (tenant_id),
            KEY idx_obs_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    echo "Table operational_board_shares créée.\n";
};
