<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
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

    if (!$tableExists('aar_report_templates')) {
        $pdo->exec(
            'CREATE TABLE aar_report_templates (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                created_by_user_id INT UNSIGNED DEFAULT NULL,
                title VARCHAR(200) NOT NULL,
                description VARCHAR(500) DEFAULT NULL,
                status VARCHAR(32) NOT NULL DEFAULT \'active\',
                fields_json JSON DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_aar_templates_tenant_status (tenant_id, status),
                CONSTRAINT fk_aar_templates_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_aar_templates_author FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    if ($tableExists('aar_reports')) {
        if (!$columnExists('aar_reports', 'mission_plan_id')) {
            try {
                $pdo->exec(
                    'ALTER TABLE aar_reports
                     ADD COLUMN mission_plan_id INT UNSIGNED DEFAULT NULL AFTER mission_cycle_id'
                );
                $pdo->exec('ALTER TABLE aar_reports ADD KEY idx_aar_reports_plan (mission_plan_id)');
            } catch (Throwable) {
            }
        }
        if (!$columnExists('aar_reports', 'template_id')) {
            try {
                $pdo->exec(
                    'ALTER TABLE aar_reports
                     ADD COLUMN template_id INT UNSIGNED DEFAULT NULL AFTER mission_plan_id'
                );
                $pdo->exec('ALTER TABLE aar_reports ADD KEY idx_aar_reports_template (template_id)');
            } catch (Throwable) {
            }
        }
        if (!$columnExists('aar_reports', 'custom_answers_json')) {
            try {
                $pdo->exec(
                    'ALTER TABLE aar_reports
                     ADD COLUMN custom_answers_json JSON DEFAULT NULL AFTER metrics_json'
                );
            } catch (Throwable) {
            }
        }

        return;
    }

    $pdo->exec(
        'CREATE TABLE aar_reports (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            mission_cycle_id INT UNSIGNED DEFAULT NULL,
            mission_plan_id INT UNSIGNED DEFAULT NULL,
            template_id INT UNSIGNED DEFAULT NULL,
            author_user_id INT UNSIGNED DEFAULT NULL,
            validated_by_user_id INT UNSIGNED DEFAULT NULL,
            title VARCHAR(200) NOT NULL,
            operation_label VARCHAR(200) DEFAULT NULL,
            status VARCHAR(32) NOT NULL DEFAULT \'pending\',
            reported_at DATETIME DEFAULT NULL,
            validated_at DATETIME DEFAULT NULL,
            mission_started_at DATETIME DEFAULT NULL,
            mission_ended_at DATETIME DEFAULT NULL,
            summary_text TEXT DEFAULT NULL,
            strengths_json JSON DEFAULT NULL,
            weaknesses_json JSON DEFAULT NULL,
            open_actions_json JSON DEFAULT NULL,
            closed_actions_json JSON DEFAULT NULL,
            scores_json JSON DEFAULT NULL,
            metrics_json JSON DEFAULT NULL,
            custom_answers_json JSON DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_aar_reports_tenant_status (tenant_id, status),
            KEY idx_aar_reports_mission (mission_cycle_id),
            KEY idx_aar_reports_plan (mission_plan_id),
            KEY idx_aar_reports_template (template_id),
            KEY idx_aar_reports_author (author_user_id),
            CONSTRAINT fk_aar_reports_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_aar_reports_mission FOREIGN KEY (mission_cycle_id) REFERENCES theatre_mission_cycles (id) ON DELETE SET NULL ON UPDATE CASCADE,
            CONSTRAINT fk_aar_reports_author FOREIGN KEY (author_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
            CONSTRAINT fk_aar_reports_validator FOREIGN KEY (validated_by_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
};
