<?php

declare(strict_types=1);

/**
 * Briefing : colonne détail a posteriori + table de commentaires sur diapositives.
 * Idempotent (MariaDB / MySQL).
 */
function ensure_tactical_briefing_slide_enrichment_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

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

    if ($tableExists($pdo, 'tactical_briefing_slides') && !$columnExists($pdo, 'tactical_briefing_slides', 'detail_text')) {
        try {
            $pdo->exec('ALTER TABLE tactical_briefing_slides ADD COLUMN detail_text TEXT NULL AFTER title');
        } catch (Throwable) {
            // Best-effort.
        }
    }

    if (!$tableExists($pdo, 'tactical_briefing_slide_comments')) {
        try {
            $pdo->exec(
                "CREATE TABLE tactical_briefing_slide_comments (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    tenant_id INT UNSIGNED NOT NULL,
                    slide_id INT UNSIGNED NOT NULL,
                    author_label VARCHAR(120) NOT NULL DEFAULT '',
                    body TEXT NOT NULL,
                    source ENUM('phone','admin','arma') NOT NULL DEFAULT 'phone',
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_tbsc_slide_created (slide_id, created_at),
                    KEY idx_tbsc_tenant_created (tenant_id, created_at),
                    CONSTRAINT tbsc_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT tbsc_slide_fk FOREIGN KEY (slide_id) REFERENCES tactical_briefing_slides (id) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } catch (Throwable) {
            // Best-effort.
        }
    }
}

return static function (PDO $pdo): void {
    ensure_tactical_briefing_slide_enrichment_schema($pdo);
};
