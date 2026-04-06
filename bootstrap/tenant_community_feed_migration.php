<?php

declare(strict_types=1);

/**
 * Fil d’activité communauté (formations, inscriptions…) + journal des alertes staff module.
 * Idempotent — appelée depuis run-migrations.php.
 */
return function (PDO $pdo): void {
    $hasFeed = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_community_feed' LIMIT 1");
    if (!$hasFeed || !$hasFeed->fetch()) {
        $pdo->exec(
            'CREATE TABLE tenant_community_feed (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            category VARCHAR(64) NOT NULL,
            title VARCHAR(255) NOT NULL,
            body TEXT,
            link_url VARCHAR(512) DEFAULT NULL,
            actor_user_id INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_tcf_tenant_created (tenant_id, created_at),
            CONSTRAINT tcf_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        echo "Table tenant_community_feed créée.\n";
    }

    $hasPing = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_staff_ping_log' LIMIT 1");
    if (!$hasPing || !$hasPing->fetch()) {
        $pdo->exec(
            'CREATE TABLE training_staff_ping_log (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            enrollment_id BIGINT UNSIGNED NOT NULL,
            module_id BIGINT UNSIGNED NOT NULL,
            ping_kind VARCHAR(32) NOT NULL DEFAULT \'module_blocked\',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_tsp_cooldown (enrollment_id, module_id, ping_kind, created_at),
            CONSTRAINT tsp_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        echo "Table training_staff_ping_log créée.\n";
    }
};
