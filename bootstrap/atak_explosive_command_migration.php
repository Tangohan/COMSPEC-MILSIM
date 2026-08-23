<?php

declare(strict_types=1);

/**
 * Déclenchement TOC des charges ACE (clacker / à la demande).
 * Idempotent — appelée depuis run-migrations.php.
 */
return static function (PDO $pdo): void {
    $hasColumn = static function (PDO $pdo, string $table, string $column): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    $tableExists = static function (PDO $pdo, string $table): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    $isNullable = static function (PDO $pdo, string $table, string $column): bool {
        $st = $pdo->prepare(
            'SELECT IS_NULLABLE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);
        $v = $st->fetchColumn();

        return is_string($v) && strtoupper($v) === 'YES';
    };

    if (!$tableExists($pdo, 'atak_explosive_timers')) {
        echo "  [ATTENTION] atak_explosive_timers absente — déclenchement TOC reporté\n";

        return;
    }

    $alters = [
        'trigger_kind' => "ALTER TABLE atak_explosive_timers ADD COLUMN trigger_kind VARCHAR(16) NOT NULL DEFAULT 'timer' AFTER fuse_seconds",
        'detonate_requested_at' => 'ALTER TABLE atak_explosive_timers ADD COLUMN detonate_requested_at DATETIME DEFAULT NULL AFTER ended_at',
        'detonate_requested_by' => "ALTER TABLE atak_explosive_timers ADD COLUMN detonate_requested_by VARCHAR(120) NOT NULL DEFAULT '' AFTER detonate_requested_at",
        'detonate_ack_at' => 'ALTER TABLE atak_explosive_timers ADD COLUMN detonate_ack_at DATETIME DEFAULT NULL AFTER detonate_requested_by',
    ];
    foreach ($alters as $col => $sql) {
        if ($hasColumn($pdo, 'atak_explosive_timers', $col)) {
            continue;
        }
        try {
            $pdo->exec($sql);
            echo "  [OK] atak_explosive_timers.{$col}\n";
        } catch (Throwable $e) {
            echo "  [ATTENTION] atak_explosive_timers.{$col} : " . $e->getMessage() . "\n";
        }
    }

    if ($hasColumn($pdo, 'atak_explosive_timers', 'detonates_at') && !$isNullable($pdo, 'atak_explosive_timers', 'detonates_at')) {
        try {
            $pdo->exec('ALTER TABLE atak_explosive_timers MODIFY detonates_at DATETIME NULL');
            echo "  [OK] atak_explosive_timers.detonates_at nullable (charges à la demande)\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] detonates_at nullable : ' . $e->getMessage() . "\n";
        }
    }

    $idxExists = static function (PDO $pdo, string $table, string $index): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $index]);

        return (bool) $st->fetchColumn();
    };
    if (!$idxExists($pdo, 'atak_explosive_timers', 'idx_explosive_timer_pending_det')) {
        try {
            $pdo->exec(
                'ALTER TABLE atak_explosive_timers
                 ADD KEY idx_explosive_timer_pending_det (tenant_id, map_id, status, detonate_requested_at)'
            );
            echo "  [OK] idx_explosive_timer_pending_det\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] idx_explosive_timer_pending_det : ' . $e->getMessage() . "\n";
        }
    }
};
