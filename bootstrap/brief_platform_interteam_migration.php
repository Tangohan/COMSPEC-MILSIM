<?php

declare(strict_types=1);

/**
 * Table platform_settings et tables missions inter-équipes.
 * Idempotent — appelée depuis run-migrations.php.
 */
return function (PDO $pdo): void {
    $hasPs = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'platform_settings' LIMIT 1");
    if (!$hasPs || !$hasPs->fetch()) {
        $pdo->exec(
            'CREATE TABLE platform_settings (
            setting_key VARCHAR(100) NOT NULL,
            value TEXT,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        echo "Table platform_settings créée.\n";
    }

    $hasM = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'interteam_missions' LIMIT 1");
    if (!$hasM || !$hasM->fetch()) {
        $pdo->exec(
            'CREATE TABLE interteam_missions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(120) NOT NULL,
            status VARCHAR(24) NOT NULL DEFAULT \'draft\',
            created_by_tenant_id INT UNSIGNED NOT NULL,
            created_by_user_id INT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY interteam_missions_slug (slug),
            KEY interteam_missions_lead_tenant (created_by_tenant_id),
            KEY interteam_missions_status (status),
            CONSTRAINT interteam_missions_tenant_fk FOREIGN KEY (created_by_tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        echo "Table interteam_missions créée.\n";
    }

    $hasP = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'interteam_mission_participants' LIMIT 1");
    if (!$hasP || !$hasP->fetch()) {
        $pdo->exec(
            'CREATE TABLE interteam_mission_participants (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            mission_id BIGINT UNSIGNED NOT NULL,
            tenant_id INT UNSIGNED NOT NULL,
            role VARCHAR(16) NOT NULL DEFAULT \'partner\',
            status VARCHAR(16) NOT NULL DEFAULT \'invited\',
            invited_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            responded_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY interteam_mp_unique (mission_id, tenant_id),
            KEY interteam_mp_tenant (tenant_id),
            KEY interteam_mp_mission_status (mission_id, status),
            CONSTRAINT interteam_mp_mission_fk FOREIGN KEY (mission_id) REFERENCES interteam_missions (id) ON DELETE CASCADE,
            CONSTRAINT interteam_mp_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        echo "Table interteam_mission_participants créée.\n";
    }
};
