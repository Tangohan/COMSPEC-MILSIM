<?php

declare(strict_types=1);

/**
 * Emails transactionnels : colonnes users, tables email_tokens, user_login_devices, email_deliveries,
 * permission invitations.send, rôle recruiter.
 * Idempotent — appelée depuis run-migrations.php.
 */

require_once dirname(__DIR__) . '/app/Support/SqlText.php';

function run_transactional_email_migration(PDO $pdo): void
{
    $stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'email_verified_at' LIMIT 1");
    if ($stmt && !$stmt->fetch()) {
        echo "Email: colonnes users.email_verified_at / email_verification_sent_at...\n";
        $pdo->exec('ALTER TABLE users ADD COLUMN email_verified_at datetime DEFAULT NULL AFTER email');
        $pdo->exec('ALTER TABLE users ADD COLUMN email_verification_sent_at datetime DEFAULT NULL AFTER email_verified_at');
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS email_tokens (
        id int unsigned NOT NULL AUTO_INCREMENT,
        tenant_id int unsigned NOT NULL,
        user_id int unsigned NOT NULL,
        purpose varchar(64) NOT NULL,
        token_hash varchar(64) NOT NULL,
        nonce varchar(64) NOT NULL,
        expires_at datetime NOT NULL,
        consumed_at datetime DEFAULT NULL,
        metadata json DEFAULT NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uk_email_tokens_hash (token_hash),
        KEY idx_email_tokens_user_purpose (user_id, purpose),
        KEY idx_email_tokens_expires (expires_at),
        CONSTRAINT email_tokens_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
        CONSTRAINT email_tokens_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS user_login_devices (
        id int unsigned NOT NULL AUTO_INCREMENT,
        user_id int unsigned NOT NULL,
        tenant_id int unsigned NOT NULL,
        fingerprint_hash varchar(64) NOT NULL,
        user_agent varchar(500) DEFAULT NULL,
        first_seen_ip varchar(45) DEFAULT NULL,
        last_seen_ip varchar(45) DEFAULT NULL,
        geo_country varchar(2) DEFAULT NULL,
        last_seen_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uk_user_device (user_id, fingerprint_hash),
        KEY tenant_id (tenant_id),
        CONSTRAINT user_login_devices_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
        CONSTRAINT user_login_devices_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS email_deliveries (
        id int unsigned NOT NULL AUTO_INCREMENT,
        tenant_id int unsigned DEFAULT NULL,
        event_code varchar(80) NOT NULL,
        recipient varchar(255) NOT NULL,
        subject varchar(500) NOT NULL,
        transport varchar(32) NOT NULL,
        status varchar(20) NOT NULL,
        provider_message_id varchar(255) DEFAULT NULL,
        error_message text,
        payload_summary json DEFAULT NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_email_deliveries_tenant_created (tenant_id, created_at),
        KEY idx_email_deliveries_event (event_code, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $chk = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'email_verified_at' LIMIT 1");
    if ($chk && $chk->fetch()) {
        $pdo->exec('UPDATE users SET email_verified_at = COALESCE(email_verified_at, created_at) WHERE email_verified_at IS NULL');
    }

    $tenants = $pdo->query('SELECT id FROM tenants');
    if (!$tenants) {
        return;
    }
    $insPerm = $pdo->prepare('INSERT INTO permissions (tenant_id, name, slug, module, scope, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
    $linkPerm = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)');

    while ($row = $tenants->fetch(PDO::FETCH_ASSOC)) {
        $tenantId = (int) $row['id'];
        $st = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'invitations.send') . ' LIMIT 1');
        $st->execute([$tenantId]);
        $permRow = $st->fetch(PDO::FETCH_ASSOC);
        if (!$permRow) {
            $insPerm->execute([$tenantId, 'Envoyer des invitations', 'invitations.send', 'recruitment', 'community']);
            $permId = (int) $pdo->lastInsertId();
        } else {
            $permId = (int) $permRow['id'];
        }

        foreach (['tenant_admin', 'community_owner'] as $slug) {
            $r = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND ' . \App\Support\SqlText::equals($pdo, 'slug') . ' LIMIT 1');
            $r->execute([$tenantId, $slug]);
            $rid = $r->fetchColumn();
            if ($rid) {
                $linkPerm->execute([(int) $rid, $permId]);
            }
        }

        $recruiter = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND ' . \App\Support\SqlText::equals($pdo, 'slug') . ' LIMIT 1');
        $recruiter->execute([$tenantId, 'recruiter']);
        $recruiterId = $recruiter->fetchColumn();
        if (!$recruiterId) {
            $pdo->prepare("INSERT INTO roles (tenant_id, name, slug, description, is_system, is_locked, role_layer, created_at)
                VALUES (?, 'Recruteur', 'recruiter', 'Invitation de membres et suivi recrutement', 1, 0, 'community', NOW())")
                ->execute([$tenantId]);
            $recruiterId = (int) $pdo->lastInsertId();
        }
        $linkPerm->execute([(int) $recruiterId, $permId]);
    }

    echo "Migration transactional email (tables + invitations.send + rôle recruiter) OK.\n";
}
