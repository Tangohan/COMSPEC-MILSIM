<?php

declare(strict_types=1);

/**
 * Sanctions granulaires : restrictions_json sur moderation_actions, extension blocked_indicators.
 */
function run_moderation_granular_sanctions_migration(PDO $pdo): void
{
    $hasColumn = static function (PDO $pdo, string $table, string $column): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    if (!$hasColumn($pdo, 'moderation_actions', 'restrictions_json')) {
        try {
            $pdo->exec('ALTER TABLE moderation_actions ADD COLUMN restrictions_json JSON DEFAULT NULL COMMENT \'Granular restrictions\' AFTER reason');
        } catch (Throwable $e) {
            echo '  [ATTENTION] moderation_actions.restrictions_json : ' . $e->getMessage() . "\n";
        }
    }

    if ($hasColumn($pdo, 'blocked_indicators', 'revoked_at')) {
        // already migrated
    } else {
        try {
            $pdo->exec('ALTER TABLE blocked_indicators ADD COLUMN revoked_at datetime DEFAULT NULL AFTER created_at');
        } catch (Throwable $e) {
            echo '  [ATTENTION] blocked_indicators.revoked_at : ' . $e->getMessage() . "\n";
        }
    }
    if (!$hasColumn($pdo, 'blocked_indicators', 'created_by_user_id')) {
        try {
            $pdo->exec('ALTER TABLE blocked_indicators ADD COLUMN created_by_user_id int unsigned DEFAULT NULL AFTER reason');
        } catch (Throwable $e) {
            echo '  [ATTENTION] blocked_indicators.created_by_user_id : ' . $e->getMessage() . "\n";
        }
    }
    if (!$hasColumn($pdo, 'blocked_indicators', 'moderation_action_id')) {
        try {
            $pdo->exec('ALTER TABLE blocked_indicators ADD COLUMN moderation_action_id int unsigned DEFAULT NULL AFTER created_by_user_id');
        } catch (Throwable $e) {
            echo '  [ATTENTION] blocked_indicators.moderation_action_id : ' . $e->getMessage() . "\n";
        }
    }

    $idx = $pdo->query("SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'blocked_indicators' AND INDEX_NAME = 'idx_blocked_active_email_tenant'");
    if ($idx && !$idx->fetchColumn()) {
        try {
            $pdo->exec('ALTER TABLE blocked_indicators ADD KEY idx_blocked_active_email_tenant (tenant_id, indicator_type, revoked_at, expires_at)');
        } catch (Throwable) {
        }
    }
}

function ensure_moderation_granular_sanctions_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $st = $pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'moderation_actions' AND COLUMN_NAME = 'restrictions_json' LIMIT 1");
    if ($st && $st->fetchColumn()) {
        $done = true;

        return;
    }
    run_moderation_granular_sanctions_migration($pdo);
    $done = true;
}
