<?php

declare(strict_types=1);

use App\Support\Migrations\SchemaInspector;

$banSchema = new SchemaInspector($pdo);
if (!$banSchema->tableExists('platform_account_bans')) {
    require_once $root . '/bootstrap/platform_account_bans_migration.php';
    run_platform_account_bans_migration($pdo);
}
foreach ([
    'rule_reference' => 'VARCHAR(160) DEFAULT NULL',
    'public_reason' => 'TEXT NULL',
    'source_tenant_id' => 'INT UNSIGNED DEFAULT NULL',
    'batch_ref' => 'CHAR(32) DEFAULT NULL',
] as $banColumn => $banDefinition) {
    $banSchema->addColumnIfMissing('platform_account_bans', $banColumn, $banDefinition);
}
$banSchema->addIndexIfMissing('platform_account_bans', 'idx_pab_batch_ref', ['batch_ref', 'lifted_at']);
if ($banSchema->tableExists('moderation_actions') && $banSchema->columnExists('moderation_actions', 'sanction_scope')) {
    $banSchema->addIndexIfMissing('moderation_actions', 'idx_ma_confirmed_platform', ['target_user_id', 'sanction_scope', 'created_at']);
}

if (!$banSchema->tableExists('platform_ban_appeals')) {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS platform_ban_appeals (
        ban_id INT UNSIGNED NOT NULL,
        statement TEXT NOT NULL,
        created_at DATETIME NOT NULL,
        decision VARCHAR(16) DEFAULT NULL,
        public_reply TEXT DEFAULT NULL,
        reviewed_at DATETIME DEFAULT NULL,
        reviewed_by INT UNSIGNED DEFAULT NULL,
        PRIMARY KEY (ban_id),
        KEY idx_pba_pending (reviewed_at, created_at),
        CONSTRAINT fk_pba_ban FOREIGN KEY (ban_id) REFERENCES platform_account_bans(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

if (!$banSchema->tableExists('platform_ban_appeal_links')) {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS platform_ban_appeal_links (
        token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        ban_id INT UNSIGNED NOT NULL,
        expires_at DATETIME NOT NULL,
        PRIMARY KEY (token_hash),
        KEY idx_pbal_expiry (expires_at),
        CONSTRAINT fk_pbal_ban FOREIGN KEY (ban_id) REFERENCES platform_account_bans(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}
