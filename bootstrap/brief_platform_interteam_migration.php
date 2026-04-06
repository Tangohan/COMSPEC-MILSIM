<?php

declare(strict_types=1);

/**
 * Paramètres plateforme pour le brief, clés section communauté par tenant, tables missions inter-équipes.
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

    $insPs = $pdo->prepare(
        'INSERT INTO platform_settings (setting_key, value, updated_at) VALUES (?, ?, NOW())
         ON DUPLICATE KEY UPDATE setting_key = setting_key'
    );
    $insPs->execute(['brief_member_access', '1']);
    $insPs->execute(['brief_member_closed_message', '']);

    $truthy = static function (mixed $raw): bool {
        if ($raw === null || $raw === '') {
            return false;
        }
        $v = strtolower(trim((string) $raw));

        return in_array($v, ['1', 'true', 'yes', 'on'], true);
    };

    try {
        $stmt = $pdo->query("SELECT tenant_id, value FROM site_settings WHERE `key` = 'forum_enabled'");
        if ($stmt) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $tid = (int) ($row['tenant_id'] ?? 0);
                if ($tid <= 0) {
                    continue;
                }
                if ($truthy($row['value'] ?? '')) {
                    continue;
                }
                $up = $pdo->prepare(
                    'INSERT INTO site_settings (tenant_id, `key`, `value`, updated_at) VALUES (?, ?, ?, NOW())
                     ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = NOW()'
                );
                $up->execute([$tid, 'forum_community_section_enabled', '0']);
                $chk = $pdo->prepare(
                    'SELECT 1 FROM site_settings WHERE tenant_id = ? AND `key` = \'forum_community_section_notice\' LIMIT 1'
                );
                $chk->execute([$tid]);
                if (!$chk->fetch()) {
                    $notice = 'Cet espace est indisponible ici pour le moment. Pour les échanges d’unité, suivez les canaux indiqués par votre encadrement.';
                    $insN = $pdo->prepare(
                        'INSERT INTO site_settings (tenant_id, `key`, `value`, updated_at) VALUES (?, \'forum_community_section_notice\', ?, NOW())'
                    );
                    $insN->execute([$tid, $notice]);
                }
            }
        }
    } catch (\Throwable) {
        // site_settings peut être absente en environnement minimal
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

    $hasG = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'interteam_mission_forum_grants' LIMIT 1");
    if (!$hasG || !$hasG->fetch()) {
        $pdo->exec(
            'CREATE TABLE interteam_mission_forum_grants (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            mission_id BIGINT UNSIGNED NOT NULL,
            grant_type VARCHAR(16) NOT NULL,
            resource_id INT UNSIGNED NOT NULL,
            home_tenant_id INT UNSIGNED NOT NULL,
            consumer_tenant_id INT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY interteam_grant_unique (mission_id, grant_type, resource_id, consumer_tenant_id),
            KEY interteam_grant_consumer (consumer_tenant_id, mission_id),
            KEY interteam_grant_home (home_tenant_id),
            CONSTRAINT interteam_grant_mission_fk FOREIGN KEY (mission_id) REFERENCES interteam_missions (id) ON DELETE CASCADE,
            CONSTRAINT interteam_grant_home_tenant_fk FOREIGN KEY (home_tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
            CONSTRAINT interteam_grant_consumer_tenant_fk FOREIGN KEY (consumer_tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        echo "Table interteam_mission_forum_grants créée.\n";
    }
};
