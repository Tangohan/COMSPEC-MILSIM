<?php

declare(strict_types=1);

/**
 * Workflow signalements forum : prise en charge, timeline, commentaires modérateurs.
 */
function run_forum_reporting_workflow_migration(PDO $pdo): void
{
    $col = static function (string $table, string $column) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    if (!$col('forum_reports', 'assigned_to')) {
        $pdo->exec("ALTER TABLE forum_reports ADD COLUMN assigned_to INT UNSIGNED NULL AFTER status");
    }
    if (!$col('forum_reports', 'assigned_at')) {
        $pdo->exec("ALTER TABLE forum_reports ADD COLUMN assigned_at DATETIME NULL AFTER assigned_to");
    }
    if (!$col('forum_reports', 'last_follow_up_action')) {
        $pdo->exec("ALTER TABLE forum_reports ADD COLUMN last_follow_up_action VARCHAR(80) NULL AFTER handled_at");
    }
    if (!$col('forum_reports', 'resolution_note')) {
        $pdo->exec("ALTER TABLE forum_reports ADD COLUMN resolution_note TEXT NULL AFTER last_follow_up_action");
    }

    try {
        $pdo->exec('ALTER TABLE forum_reports ADD CONSTRAINT forum_reports_assigned_to_fk FOREIGN KEY (assigned_to) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE');
    } catch (\Throwable) {
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS forum_report_timeline (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            report_id INT UNSIGNED NOT NULL,
            actor_user_id INT UNSIGNED NULL,
            event_type VARCHAR(60) NOT NULL,
            event_label VARCHAR(160) NOT NULL,
            event_detail TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_forum_report_timeline_lookup (tenant_id, report_id, id DESC),
            KEY idx_forum_report_timeline_actor (actor_user_id),
            CONSTRAINT fk_forum_report_timeline_report FOREIGN KEY (report_id) REFERENCES forum_reports (id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_forum_report_timeline_actor FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

