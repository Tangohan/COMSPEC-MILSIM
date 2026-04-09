<?php

declare(strict_types=1);

/**
 * Hub coopération inter-unités : colonnes mission, événements, réunions, consentements, posts coop, co-pilotes.
 * Idempotent — appelée depuis run-migrations.php.
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
        $adds = [
            'cooperation_starts_at' => 'DATETIME DEFAULT NULL',
            'cooperation_ends_at' => 'DATETIME DEFAULT NULL',
            'coop_forum_category_id' => 'INT UNSIGNED DEFAULT NULL',
            'coop_forum_topic_id' => 'INT UNSIGNED DEFAULT NULL',
            'meeting_replay_url' => 'VARCHAR(500) DEFAULT NULL',
            'atak_endpoint_primary' => 'VARCHAR(255) DEFAULT NULL',
            'atak_endpoint_partner' => 'VARCHAR(255) DEFAULT NULL',
            'liaison_notes' => 'TEXT DEFAULT NULL',
        ];
        foreach ($adds as $c => $def) {
            if (!$col($pdo, 'interteam_missions', $c)) {
                $pdo->exec("ALTER TABLE interteam_missions ADD COLUMN `{$c}` {$def}");
                echo "interteam_missions.{$c} ajouté.\n";
            }
        }
        $phaseAdds = [
            'cooperation_phase' => 'VARCHAR(32) DEFAULT NULL',
            'cooperation_priority' => "VARCHAR(24) NOT NULL DEFAULT 'routine'",
            'cooperation_typology' => 'VARCHAR(48) DEFAULT NULL',
            'proposal_deadline_at' => 'DATETIME DEFAULT NULL',
            'requesting_tenant_id' => 'INT UNSIGNED DEFAULT NULL',
        ];
        foreach ($phaseAdds as $c => $def) {
            if (!$col($pdo, 'interteam_missions', $c)) {
                $pdo->exec("ALTER TABLE interteam_missions ADD COLUMN `{$c}` {$def}");
                echo "interteam_missions.{$c} ajouté.\n";
            }
        }
        $negotiationAdds = [
            'counter_proposal_json' => 'JSON DEFAULT NULL',
            'counter_proposal_submitted_at' => 'DATETIME DEFAULT NULL',
            'counter_proposal_tenant_id' => 'INT UNSIGNED DEFAULT NULL',
            'counter_proposal_status' => 'VARCHAR(24) DEFAULT NULL',
            'proposal_deadline_notified_at' => 'DATETIME DEFAULT NULL',
        ];
        foreach ($negotiationAdds as $c => $def) {
            if (!$col($pdo, 'interteam_missions', $c)) {
                $pdo->exec("ALTER TABLE interteam_missions ADD COLUMN `{$c}` {$def}");
                echo "interteam_missions.{$c} ajouté.\n";
            }
        }
        if ($col($pdo, 'interteam_missions', 'cooperation_phase')) {
            $pdo->exec(
                "UPDATE interteam_missions SET cooperation_phase = CASE status
                    WHEN 'draft' THEN 'draft'
                    WHEN 'pending' THEN 'proposed'
                    WHEN 'active' THEN 'active'
                    WHEN 'archived' THEN 'closed'
                    ELSE 'draft' END
                WHERE cooperation_phase IS NULL OR cooperation_phase = ''"
            );
            $pdo->exec(
                'UPDATE interteam_missions SET requesting_tenant_id = created_by_tenant_id
                WHERE requesting_tenant_id IS NULL'
            );
        }
    }

    if ($col($pdo, 'interteam_mission_meetings', 'id')) {
        $mmAdds = [
            'meeting_title' => 'VARCHAR(255) DEFAULT NULL',
            'meeting_agenda' => 'TEXT DEFAULT NULL',
            'scheduled_at' => 'DATETIME DEFAULT NULL',
        ];
        foreach ($mmAdds as $c => $def) {
            if (!$col($pdo, 'interteam_mission_meetings', $c)) {
                $pdo->exec("ALTER TABLE interteam_mission_meetings ADD COLUMN `{$c}` {$def}");
                echo "interteam_mission_meetings.{$c} ajouté.\n";
            }
        }
    }

    $hasRex = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'interteam_mission_rex' LIMIT 1");
    if (!$hasRex || !$hasRex->fetch()) {
        $pdo->exec(
            'CREATE TABLE interteam_mission_rex (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            mission_id BIGINT UNSIGNED NOT NULL,
            tenant_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            worked_well TEXT DEFAULT NULL,
            failed_aspects TEXT DEFAULT NULL,
            coordination_incidents TEXT DEFAULT NULL,
            sharing_difficulties TEXT DEFAULT NULL,
            technical_difficulties TEXT DEFAULT NULL,
            recommendations TEXT DEFAULT NULL,
            rating_fluidity TINYINT UNSIGNED DEFAULT NULL,
            rating_security TINYINT UNSIGNED DEFAULT NULL,
            rating_usefulness TINYINT UNSIGNED DEFAULT NULL,
            rating_reactivity TINYINT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY interteam_rex_mission_tenant (mission_id, tenant_id),
            KEY interteam_rex_mission (mission_id),
            CONSTRAINT interteam_rex_mission_fk FOREIGN KEY (mission_id) REFERENCES interteam_missions (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        echo "Table interteam_mission_rex créée.\n";
    }

    $hasEvents = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'interteam_mission_events' LIMIT 1");
    if (!$hasEvents || !$hasEvents->fetch()) {
        $pdo->exec(
            'CREATE TABLE interteam_mission_events (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            mission_id BIGINT UNSIGNED NOT NULL,
            actor_user_id INT UNSIGNED NOT NULL,
            actor_tenant_id INT UNSIGNED NOT NULL,
            event_type VARCHAR(64) NOT NULL,
            payload_json JSON DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY interteam_ev_mission (mission_id, created_at),
            KEY interteam_ev_actor (actor_user_id),
            CONSTRAINT interteam_ev_mission_fk FOREIGN KEY (mission_id) REFERENCES interteam_missions (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        echo "Table interteam_mission_events créée.\n";
    }

    $hasMeet = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'interteam_mission_meetings' LIMIT 1");
    if (!$hasMeet || !$hasMeet->fetch()) {
        $pdo->exec(
            'CREATE TABLE interteam_mission_meetings (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            mission_id BIGINT UNSIGNED NOT NULL,
            created_by_user_id INT UNSIGNED NOT NULL,
            started_at DATETIME DEFAULT NULL,
            ended_at DATETIME DEFAULT NULL,
            replay_url VARCHAR(500) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY interteam_mm_mission (mission_id),
            CONSTRAINT interteam_mm_mission_fk FOREIGN KEY (mission_id) REFERENCES interteam_missions (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        echo "Table interteam_mission_meetings créée.\n";
    }

    $hasConsent = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'interteam_cooperation_consents' LIMIT 1");
    if (!$hasConsent || !$hasConsent->fetch()) {
        $pdo->exec(
            'CREATE TABLE interteam_cooperation_consents (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            mission_id BIGINT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            tenant_id INT UNSIGNED NOT NULL,
            selections_json JSON DEFAULT NULL,
            otp_verified_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY interteam_consent_uq (mission_id, user_id),
            KEY interteam_consent_user (user_id),
            CONSTRAINT interteam_consent_mission_fk FOREIGN KEY (mission_id) REFERENCES interteam_missions (id) ON DELETE CASCADE,
            CONSTRAINT interteam_consent_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        echo "Table interteam_cooperation_consents créée.\n";
    }

    if ($col($pdo, 'forum_posts', 'id') && !$col($pdo, 'forum_posts', 'coop_source_tenant_id')) {
        try {
            $pdo->exec(
                'ALTER TABLE forum_posts ADD COLUMN coop_source_tenant_id INT UNSIGNED DEFAULT NULL,
                ADD KEY forum_posts_coop_src (coop_source_tenant_id),
                ADD CONSTRAINT forum_posts_coop_src_fk FOREIGN KEY (coop_source_tenant_id) REFERENCES tenants (id) ON DELETE SET NULL'
            );
            echo "forum_posts.coop_source_tenant_id ajouté.\n";
        } catch (\Throwable $e) {
            echo '  [ATTENTION] forum_posts coop column : ' . $e->getMessage() . "\n";
        }
    }
};
