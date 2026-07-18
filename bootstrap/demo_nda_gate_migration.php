<?php

declare(strict_types=1);

/**
 * Portail d’engagement démo (NDA) : visites par adresse réseau.
 * Idempotent — appelée depuis run-migrations.php.
 */
return function (PDO $pdo): void {
    $chk = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'demo_nda_visits' LIMIT 1");
    if ($chk && $chk->fetch()) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE demo_nda_visits (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            ip_address VARCHAR(45) NOT NULL,
            first_seen_at DATETIME NOT NULL,
            claim_expires_at DATETIME NOT NULL,
            status ENUM('pending','granted','expired') NOT NULL DEFAULT 'pending',
            granted_at DATETIME DEFAULT NULL,
            session_expires_at DATETIME DEFAULT NULL,
            access_token_hash CHAR(64) DEFAULT NULL,
            user_agent VARCHAR(512) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_demo_nda_ip (ip_address),
            KEY idx_demo_nda_status_seen (status, first_seen_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    echo "Table demo_nda_visits créée.\n";
};
