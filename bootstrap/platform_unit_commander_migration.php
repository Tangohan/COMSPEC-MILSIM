<?php

declare(strict_types=1);

/**
 * Invitations, modération, sécurité, événements communauté, métriques — idempotent via run-migrations.php.
 */
function run_platform_unit_commander_migration(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS community_invitations (
        id int unsigned NOT NULL AUTO_INCREMENT,
        tenant_id int unsigned NOT NULL,
        email varchar(255) NOT NULL,
        token_hash varchar(64) NOT NULL,
        role_id int unsigned DEFAULT NULL,
        invited_by_user_id int unsigned NOT NULL,
        status varchar(32) NOT NULL DEFAULT 'pending',
        expires_at datetime NOT NULL,
        accepted_user_id int unsigned DEFAULT NULL,
        accepted_at datetime DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY token_hash (token_hash),
        KEY tenant_email (tenant_id, email),
        KEY tenant_status (tenant_id, status),
        CONSTRAINT fk_ci_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
        CONSTRAINT fk_ci_inviter FOREIGN KEY (invited_by_user_id) REFERENCES users (id) ON DELETE CASCADE,
        CONSTRAINT fk_ci_accepted FOREIGN KEY (accepted_user_id) REFERENCES users (id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS moderation_cases (
        id int unsigned NOT NULL AUTO_INCREMENT,
        tenant_id int unsigned NOT NULL,
        subject_user_id int unsigned NOT NULL,
        opened_by_user_id int unsigned NOT NULL,
        status varchar(32) NOT NULL DEFAULT 'open',
        priority varchar(20) DEFAULT 'normal',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        closed_at datetime DEFAULT NULL,
        PRIMARY KEY (id),
        KEY tenant_subject (tenant_id, subject_user_id),
        CONSTRAINT fk_mc_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
        CONSTRAINT fk_mc_subject FOREIGN KEY (subject_user_id) REFERENCES users (id) ON DELETE CASCADE,
        CONSTRAINT fk_mc_opener FOREIGN KEY (opened_by_user_id) REFERENCES users (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS moderation_actions (
        id int unsigned NOT NULL AUTO_INCREMENT,
        case_id int unsigned DEFAULT NULL,
        tenant_id int unsigned NOT NULL,
        target_user_id int unsigned NOT NULL,
        actor_user_id int unsigned NOT NULL,
        action_type varchar(32) NOT NULL,
        reason text,
        expires_at datetime DEFAULT NULL,
        revoked_at datetime DEFAULT NULL,
        revoked_by_user_id int unsigned DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY tenant_target (tenant_id, target_user_id),
        KEY active_revoked (tenant_id, target_user_id, revoked_at),
        CONSTRAINT fk_ma_case FOREIGN KEY (case_id) REFERENCES moderation_cases (id) ON DELETE SET NULL,
        CONSTRAINT fk_ma_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
        CONSTRAINT fk_ma_target FOREIGN KEY (target_user_id) REFERENCES users (id) ON DELETE CASCADE,
        CONSTRAINT fk_ma_actor FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS moderation_evidence (
        id int unsigned NOT NULL AUTO_INCREMENT,
        case_id int unsigned NOT NULL,
        tenant_id int unsigned NOT NULL,
        url varchar(1000) DEFAULT NULL,
        notes text,
        created_by_user_id int unsigned NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY case_id (case_id),
        CONSTRAINT fk_me_case FOREIGN KEY (case_id) REFERENCES moderation_cases (id) ON DELETE CASCADE,
        CONSTRAINT fk_me_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
        CONSTRAINT fk_me_author FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS security_events (
        id int unsigned NOT NULL AUTO_INCREMENT,
        tenant_id int unsigned DEFAULT NULL,
        user_id int unsigned DEFAULT NULL,
        event_type varchar(64) NOT NULL,
        ip varchar(45) DEFAULT NULL,
        user_agent varchar(500) DEFAULT NULL,
        meta_json text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY tenant_type (tenant_id, event_type),
        KEY created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS blocked_indicators (
        id int unsigned NOT NULL AUTO_INCREMENT,
        tenant_id int unsigned DEFAULT NULL,
        indicator_type varchar(32) NOT NULL,
        value_hash varchar(64) NOT NULL,
        scope varchar(16) NOT NULL DEFAULT 'tenant',
        reason varchar(500) DEFAULT NULL,
        expires_at datetime DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY scope_hash (scope, tenant_id, indicator_type, value_hash),
        KEY expires (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS community_events (
        id int unsigned NOT NULL AUTO_INCREMENT,
        tenant_id int unsigned NOT NULL,
        title varchar(255) NOT NULL,
        description text,
        location varchar(255) DEFAULT NULL,
        campaign_tag varchar(100) DEFAULT NULL,
        event_type varchar(32) NOT NULL DEFAULT 'evenement',
        starts_at datetime NOT NULL,
        ends_at datetime DEFAULT NULL,
        created_by_user_id int unsigned NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        cancelled_at datetime DEFAULT NULL,
        cancelled_reason varchar(500) DEFAULT NULL,
        PRIMARY KEY (id),
        KEY tenant_starts (tenant_id, starts_at),
        CONSTRAINT fk_ce_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
        CONSTRAINT fk_ce_creator FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $ceCancelled = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'community_events' AND COLUMN_NAME = 'cancelled_at'");
    if ($ceCancelled && !$ceCancelled->fetch()) {
        try {
            $pdo->exec("ALTER TABLE community_events ADD COLUMN event_type varchar(32) NOT NULL DEFAULT 'evenement' AFTER campaign_tag");
        } catch (\PDOException) {
        }
        try {
            $pdo->exec("ALTER TABLE community_events ADD COLUMN cancelled_at datetime DEFAULT NULL AFTER updated_at");
            $pdo->exec("ALTER TABLE community_events ADD COLUMN cancelled_reason varchar(500) DEFAULT NULL AFTER cancelled_at");
        } catch (\PDOException $e) {
            echo '  [ATTENTION] community_events annulation : ' . $e->getMessage() . "\n";
        }
    }

    $idxCeCreated = $pdo->query("SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'community_events' AND INDEX_NAME = 'ce_tenant_created'");
    if ($idxCeCreated && !$idxCeCreated->fetch()) {
        try {
            $pdo->exec('ALTER TABLE community_events ADD KEY ce_tenant_created (tenant_id, created_at)');
            echo "Index community_events.ce_tenant_created (tenant_id, created_at).\n";
        } catch (\PDOException $e) {
            echo '  [ATTENTION] Index ce_tenant_created : ' . $e->getMessage() . "\n";
        }
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS community_event_rsvps (
        id int unsigned NOT NULL AUTO_INCREMENT,
        event_id int unsigned NOT NULL,
        user_id int unsigned NOT NULL,
        status varchar(16) NOT NULL DEFAULT 'yes',
        checked_in_at datetime DEFAULT NULL,
        reminder_sent_at datetime DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY event_user (event_id, user_id),
        KEY idx_rsvp_reminder (event_id, reminder_sent_at),
        CONSTRAINT fk_rsvp_event FOREIGN KEY (event_id) REFERENCES community_events (id) ON DELETE CASCADE,
        CONSTRAINT fk_rsvp_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $rsvpCi = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'community_event_rsvps' AND COLUMN_NAME = 'checked_in_at'");
    if ($rsvpCi && !$rsvpCi->fetch()) {
        try {
            $pdo->exec("ALTER TABLE community_event_rsvps ADD COLUMN checked_in_at datetime DEFAULT NULL AFTER status");
            $pdo->exec("ALTER TABLE community_event_rsvps ADD COLUMN reminder_sent_at datetime DEFAULT NULL AFTER checked_in_at");
            $pdo->exec('ALTER TABLE community_event_rsvps ADD KEY idx_rsvp_reminder (event_id, reminder_sent_at)');
        } catch (\PDOException $e) {
            echo '  [ATTENTION] community_event_rsvps pointage : ' . $e->getMessage() . "\n";
        }
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS platform_usage_events (
        id bigint unsigned NOT NULL AUTO_INCREMENT,
        tenant_id int unsigned NOT NULL,
        user_id int unsigned DEFAULT NULL,
        feature_key varchar(64) NOT NULL,
        action varchar(64) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY tenant_day (tenant_id, created_at),
        KEY feature (feature_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $cc = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenants' AND COLUMN_NAME = 'community_code'");
    if ($cc && !$cc->fetch()) {
        $pdo->exec("ALTER TABLE tenants ADD COLUMN community_code varchar(64) DEFAULT NULL COMMENT 'Code court unique (MAJUSCULES/tirets) pour rejoindre la communauté' AFTER slug");
        try {
            $pdo->exec('ALTER TABLE tenants ADD UNIQUE KEY tenants_community_code (community_code)');
        } catch (\PDOException) {
            // colonne ajoutée ailleurs
        }
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS referral_codes (
        id int unsigned NOT NULL AUTO_INCREMENT,
        user_id int unsigned NOT NULL,
        code varchar(32) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_referral_codes_user (user_id),
        UNIQUE KEY uq_referral_codes_code (code),
        CONSTRAINT fk_referral_codes_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS referral_attributions (
        id bigint unsigned NOT NULL AUTO_INCREMENT,
        referrer_user_id int unsigned NOT NULL,
        referred_tenant_id int unsigned DEFAULT NULL,
        event_type varchar(32) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_referral_attr (referrer_user_id, referred_tenant_id, event_type),
        KEY idx_referrer (referrer_user_id),
        KEY idx_tenant (referred_tenant_id),
        CONSTRAINT fk_referral_attr_referrer FOREIGN KEY (referrer_user_id) REFERENCES users (id) ON DELETE CASCADE,
        CONSTRAINT fk_referral_attr_tenant FOREIGN KEY (referred_tenant_id) REFERENCES tenants (id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    try {
        $st = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'community_invitations' AND COLUMN_NAME = 'invitation_payload' LIMIT 1");
        if ($st && !$st->fetchColumn()) {
            $pdo->exec("ALTER TABLE community_invitations ADD COLUMN invitation_payload JSON DEFAULT NULL COMMENT 'Unite et role metier prevus a l acceptation' AFTER role_id");
        }
    } catch (\Throwable) {
    }
}

/**
 * Vérifie la présence des tables clés ; si une manque (migrations jamais passées en prod), exécute la migration idempotente.
 * À appeler depuis les dépôts (modération, parrainage, etc.) pour éviter les 1146 sans passage obligatoire par la CLI.
 */
function ensure_platform_unit_commander_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $required = [
        'community_invitations',
        'moderation_cases',
        'moderation_actions',
        'referral_codes',
        'referral_attributions',
    ];
    $missing = false;
    try {
        foreach ($required as $table) {
            $q = $pdo->quote($table);
            $st = $pdo->query(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = {$q} LIMIT 1"
            );
            if (!$st || !$st->fetchColumn()) {
                $missing = true;
                break;
            }
        }
    } catch (\Throwable) {
        $missing = true;
    }
    if (!$missing) {
        $done = true;

        return;
    }
    run_platform_unit_commander_migration($pdo);
    $done = true;
}
