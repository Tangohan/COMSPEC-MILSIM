<?php

declare(strict_types=1);

/**
 * Tables de suivi des tâches planifiées (cron).
 * Idempotent — appelée depuis run-migrations.php.
 */
return function (PDO $pdo): void {
    $chk = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cron_job_runs' LIMIT 1");
    if (!$chk || !$chk->fetch()) {
        $pdo->exec(
            "CREATE TABLE cron_job_runs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                job_key VARCHAR(64) NOT NULL,
                started_at DATETIME NOT NULL,
                finished_at DATETIME DEFAULT NULL,
                status ENUM('running','ok','error') NOT NULL DEFAULT 'running',
                summary VARCHAR(512) DEFAULT NULL,
                details_json JSON DEFAULT NULL,
                trigger_source VARCHAR(32) NOT NULL DEFAULT 'cli',
                PRIMARY KEY (id),
                KEY idx_cron_runs_job_started (job_key, started_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        echo "Table cron_job_runs créée.\n";
    }

    $chk2 = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cron_notification_log' LIMIT 1");
    if (!$chk2 || !$chk2->fetch()) {
        $pdo->exec(
            "CREATE TABLE cron_notification_log (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                job_key VARCHAR(64) NOT NULL,
                subject_type VARCHAR(64) NOT NULL,
                subject_id VARCHAR(64) NOT NULL,
                channel VARCHAR(32) NOT NULL DEFAULT 'email',
                recipient VARCHAR(255) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_cron_notif (job_key, subject_type, subject_id, channel),
                KEY idx_cron_notif_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        echo "Table cron_notification_log créée.\n";
    }
};
