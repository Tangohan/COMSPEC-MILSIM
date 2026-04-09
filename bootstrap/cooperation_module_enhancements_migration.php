<?php

declare(strict_types=1);

/**
 * Extensions module coopération inter-unités : snapshots, gouvernance, consentement renforcé,
 * journal notifications, forum coop enrichi, clôture structurée, réunions, ORBAT/ATAK.
 * Idempotent — invoqué depuis run-migrations.php.
 */
return function (PDO $pdo): void {
    $col = static function (PDO $pdo, string $table, string $column): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    if ($col($pdo, 'interteam_missions', 'id')) {
        $missionAdds = [
            'activation_snapshot_json' => 'JSON DEFAULT NULL',
            'suspensive_conditions_json' => 'JSON DEFAULT NULL',
            'exchange_lock_mode' => "VARCHAR(24) NOT NULL DEFAULT 'none'",
            'closure_summary' => 'TEXT DEFAULT NULL',
            'closure_motive' => 'VARCHAR(500) DEFAULT NULL',
            'archive_retention' => "VARCHAR(24) DEFAULT 'standard'",
            'atak_primary_label' => 'VARCHAR(160) DEFAULT NULL',
            'atak_partner_label' => 'VARCHAR(160) DEFAULT NULL',
            'atak_bascule_notes' => 'TEXT DEFAULT NULL',
            'atak_sync_status' => "VARCHAR(32) DEFAULT NULL",
            'competency_needs_json' => 'JSON DEFAULT NULL',
            'cooperation_checklist_json' => 'JSON DEFAULT NULL',
            'template_source_mission_id' => 'BIGINT UNSIGNED DEFAULT NULL',
            'crisis_mode' => 'TINYINT UNSIGNED NOT NULL DEFAULT 0',
        ];
        foreach ($missionAdds as $c => $def) {
            if (!$col($pdo, 'interteam_missions', $c)) {
                $pdo->exec("ALTER TABLE interteam_missions ADD COLUMN `{$c}` {$def}");
                echo "interteam_missions.{$c} ajouté.\n";
            }
        }
    }

    if ($col($pdo, 'interteam_mission_meetings', 'id')) {
        $mm = [
            'meeting_state' => "VARCHAR(24) NOT NULL DEFAULT 'planned'",
            'expected_participants_note' => 'TEXT DEFAULT NULL',
            'minutes_text' => 'TEXT DEFAULT NULL',
        ];
        foreach ($mm as $c => $def) {
            if (!$col($pdo, 'interteam_mission_meetings', $c)) {
                $pdo->exec("ALTER TABLE interteam_mission_meetings ADD COLUMN `{$c}` {$def}");
                echo "interteam_mission_meetings.{$c} ajouté.\n";
            }
        }
    }

    $hasMembers = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'interteam_mission_members' LIMIT 1");
    if (!$hasMembers || !$hasMembers->fetch()) {
        $pdo->exec(
            'CREATE TABLE interteam_mission_members (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            mission_id BIGINT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            tenant_id INT UNSIGNED NOT NULL,
            role_slug VARCHAR(48) NOT NULL,
            assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            assigned_by_user_id INT UNSIGNED DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_imm_mission_user (mission_id, user_id),
            KEY idx_imm_mission (mission_id),
            KEY idx_imm_tenant (tenant_id),
            CONSTRAINT fk_imm_mission FOREIGN KEY (mission_id) REFERENCES interteam_missions (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        echo "Table interteam_mission_members créée.\n";
    }

    if ($col($pdo, 'interteam_cooperation_consents', 'id')) {
        $consAdds = [
            'consent_expires_at' => 'DATETIME DEFAULT NULL',
            'justification_sensitive' => 'TEXT DEFAULT NULL',
        ];
        foreach ($consAdds as $c => $def) {
            if (!$col($pdo, 'interteam_cooperation_consents', $c)) {
                $pdo->exec("ALTER TABLE interteam_cooperation_consents ADD COLUMN `{$c}` {$def}");
                echo "interteam_cooperation_consents.{$c} ajouté.\n";
            }
        }
    }

    $hasOtp = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'interteam_cooperation_otp_attempts' LIMIT 1");
    if (!$hasOtp || !$hasOtp->fetch()) {
        $pdo->exec(
            'CREATE TABLE interteam_cooperation_otp_attempts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            mission_id BIGINT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            outcome VARCHAR(16) NOT NULL,
            ip_prefix VARCHAR(45) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_otp_user_mission (user_id, mission_id, created_at),
            CONSTRAINT fk_otp_mission FOREIGN KEY (mission_id) REFERENCES interteam_missions (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        echo "Table interteam_cooperation_otp_attempts créée.\n";
    }

    $hasOutbox = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cooperation_notification_outbox' LIMIT 1");
    if (!$hasOutbox || !$hasOutbox->fetch()) {
        $pdo->exec(
            'CREATE TABLE cooperation_notification_outbox (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED DEFAULT NULL,
            user_id INT UNSIGNED DEFAULT NULL,
            event_key VARCHAR(96) NOT NULL,
            payload_json JSON DEFAULT NULL,
            aggregation_key VARCHAR(160) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            processed_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_cno_proc (processed_at, created_at),
            KEY idx_cno_agg (aggregation_key, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        echo "Table cooperation_notification_outbox créée.\n";
    }

    $hasTpl = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cooperation_mission_templates' LIMIT 1");
    if (!$hasTpl || !$hasTpl->fetch()) {
        $pdo->exec(
            'CREATE TABLE cooperation_mission_templates (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            default_typology VARCHAR(48) DEFAULT NULL,
            default_priority VARCHAR(24) DEFAULT NULL,
            checklist_json JSON DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_cmt_tenant (tenant_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        echo "Table cooperation_mission_templates créée.\n";
    }

    if ($col($pdo, 'forum_posts', 'id')) {
        $fpAdds = [
            'coop_official_kind' => 'VARCHAR(32) DEFAULT NULL',
            'is_draft' => 'TINYINT UNSIGNED NOT NULL DEFAULT 0',
            'coop_mission_role' => 'VARCHAR(48) DEFAULT NULL',
        ];
        foreach ($fpAdds as $c => $def) {
            if (!$col($pdo, 'forum_posts', $c)) {
                $pdo->exec("ALTER TABLE forum_posts ADD COLUMN `{$c}` {$def}");
                echo "forum_posts.{$c} ajouté.\n";
            }
        }
    }
};
