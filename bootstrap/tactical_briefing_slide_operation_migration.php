<?php

declare(strict_types=1);

/**
 * Briefing : rattachement optionnel d'une diapositive à une opération Athena (operations.id).
 * NULL = diapositive commune à toutes les opérations. Idempotent (MariaDB / MySQL).
 */
function ensure_tactical_briefing_slide_operation_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $tableExists = static function (PDO $pdo, string $table): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    $columnExists = static function (PDO $pdo, string $table, string $column): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    $indexExists = static function (PDO $pdo, string $table, string $index): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $index]);

        return (bool) $st->fetchColumn();
    };

    if (!$tableExists($pdo, 'tactical_briefing_slides')) {
        return;
    }

    if (!$columnExists($pdo, 'tactical_briefing_slides', 'operation_id')) {
        try {
            $pdo->exec('ALTER TABLE tactical_briefing_slides ADD COLUMN operation_id INT UNSIGNED NULL AFTER tenant_id');
        } catch (Throwable) {
            // Best-effort.
        }
    }

    if ($columnExists($pdo, 'tactical_briefing_slides', 'operation_id')
        && !$indexExists($pdo, 'tactical_briefing_slides', 'idx_tbs_tenant_operation')) {
        try {
            $pdo->exec('ALTER TABLE tactical_briefing_slides ADD KEY idx_tbs_tenant_operation (tenant_id, operation_id)');
        } catch (Throwable) {
            // Best-effort.
        }
    }
}

return static function (PDO $pdo): void {
    ensure_tactical_briefing_slide_operation_schema($pdo);
};
