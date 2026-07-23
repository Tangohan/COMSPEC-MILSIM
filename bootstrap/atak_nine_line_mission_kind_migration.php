<?php

declare(strict_types=1);

/**
 * Colonne mission_kind sur atak_nine_line (cas | medevac).
 * Idempotent.
 */
return static function (PDO $pdo): void {
    $columnExists = static function (PDO $pdo, string $table, string $column): bool {
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

    if (!$tableExists($pdo, 'atak_nine_line')) {
        echo "  [ATTENTION] atak_nine_line absente\n";

        return;
    }

    if (!$columnExists($pdo, 'atak_nine_line', 'mission_kind')) {
        $pdo->exec(
            "ALTER TABLE atak_nine_line
             ADD COLUMN mission_kind VARCHAR(32) NOT NULL DEFAULT 'cas'
             AFTER map_id"
        );
        echo "  + atak_nine_line.mission_kind\n";
    }

    try {
        $pdo->exec('UPDATE atak_nine_line SET mission_kind = \'cas\' WHERE mission_kind IS NULL OR mission_kind = \'\'');
    } catch (Throwable) {
    }
};
