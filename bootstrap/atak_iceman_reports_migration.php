<?php

declare(strict_types=1);

/**
 * Rapports ATAK Enhanced (Iceman) : types TIC / Eagle Down / BDA / FRAGO / SALUTE
 * persistés dans atak_tactical_reports (lien API /api/atak/reports).
 *
 * Idempotent — appelée depuis run-migrations.php.
 */
return static function (PDO $pdo, ?callable $log = null): void {
    $say = static function (string $message) use ($log): void {
        if ($log !== null) {
            $log($message);
        }
    };

    $tableExists = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    $columnExists = static function (string $table, string $column) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    $indexExists = static function (string $table, string $index) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $index]);

        return (bool) $st->fetchColumn();
    };

    if (!$tableExists('atak_tactical_reports')) {
        $say("  atak_tactical_reports absente — schéma modules ATAK d’abord.");

        return;
    }

    $pdo->exec("ALTER TABLE atak_tactical_reports
        MODIFY COLUMN report_type VARCHAR(32) NOT NULL DEFAULT 'OTHER'");

    if ($tableExists('atak_report_templates')) {
        $pdo->exec("ALTER TABLE atak_report_templates
            MODIFY COLUMN report_type VARCHAR(32) NOT NULL");
    }

    if (!$columnExists('atak_tactical_reports', 'source_chat_id')) {
        $pdo->exec("ALTER TABLE atak_tactical_reports
            ADD COLUMN source_chat_id INT UNSIGNED NULL AFTER submitter_steam_id");
        $say('  colonne source_chat_id ajoutée.');
    }

    if (!$indexExists('atak_tactical_reports', 'uk_atak_report_source_chat')) {
        $pdo->exec(
            'CREATE UNIQUE INDEX uk_atak_report_source_chat
             ON atak_tactical_reports (tenant_id, source_chat_id)'
        );
        $say('  index uk_atak_report_source_chat créé.');
    }
};
