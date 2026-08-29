<?php

declare(strict_types=1);

/**
 * Retours UI globaux (notation page + questionnaire) et flash_info_detailed sur planning_entries.
 */
return static function (PDO $pdo): void {
    $tableExists = static function (PDO $pdo, string $table): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if (!$tableExists($pdo, 'platform_page_ratings')) {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS platform_page_ratings (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                page_key VARCHAR(255) NOT NULL,
                page_path VARCHAR(500) NOT NULL DEFAULT '',
                page_title VARCHAR(255) NOT NULL DEFAULT '',
                rating TINYINT UNSIGNED NOT NULL,
                comment TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_ppr_tenant_user_page (tenant_id, user_id, page_key),
                KEY idx_ppr_tenant_page (tenant_id, page_key),
                KEY idx_ppr_updated (updated_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$tableExists($pdo, 'platform_ux_survey_responses')) {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS platform_ux_survey_responses (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                page_key VARCHAR(255) NOT NULL,
                page_path VARCHAR(500) NOT NULL DEFAULT '',
                page_title VARCHAR(255) NOT NULL DEFAULT '',
                ease_rating TINYINT UNSIGNED NOT NULL DEFAULT 0,
                clarity_rating TINYINT UNSIGNED NOT NULL DEFAULT 0,
                design_rating TINYINT UNSIGNED NOT NULL DEFAULT 0,
                usefulness_rating TINYINT UNSIGNED NOT NULL DEFAULT 0,
                issues_json JSON NULL,
                improvement_text TEXT NULL,
                would_recommend TINYINT(1) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_pux_tenant_user_page (tenant_id, user_id, page_key),
                KEY idx_pux_tenant_page (tenant_id, page_key),
                KEY idx_pux_updated (updated_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if ($tableExists($pdo, 'planning_entries')) {
        $st = $pdo->query(
            "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'planning_entries' AND COLUMN_NAME = 'entry_type' LIMIT 1"
        );
        $colType = $st ? (string) ($st->fetchColumn() ?: '') : '';
        if ($colType !== '' && !str_contains($colType, 'flash_info_detailed')) {
            $pdo->exec(
                "ALTER TABLE planning_entries
                 MODIFY COLUMN entry_type ENUM(
                    'permanence','info','mission','task','formation',
                    'manifestation','flash_info','flash_info_detailed'
                 ) NOT NULL"
            );
        }
    }
};
