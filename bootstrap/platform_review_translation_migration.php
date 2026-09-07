<?php

declare(strict_types=1);

/**
 * Avis sur la plateforme (modal site) et propositions de traduction.
 */
return static function (PDO $pdo): void {
    $tableExists = static function (PDO $pdo, string $table): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if (!$tableExists($pdo, 'platform_reviews')) {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS platform_reviews (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT UNSIGNED NULL,
                user_id INT UNSIGNED NOT NULL,
                score TINYINT UNSIGNED NULL,
                usage_kind VARCHAR(32) NOT NULL DEFAULT '',
                highlights TEXT NULL,
                improvements TEXT NULL,
                submitted_at DATETIME NULL,
                snoozed_until DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_platform_reviews_user (user_id),
                KEY idx_platform_reviews_submitted (submitted_at),
                KEY idx_platform_reviews_score (score)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$tableExists($pdo, 'translation_suggestions')) {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS translation_suggestions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT UNSIGNED NULL,
                user_id INT UNSIGNED NOT NULL,
                locale VARCHAR(8) NOT NULL,
                area VARCHAR(32) NOT NULL DEFAULT 'other',
                original_text VARCHAR(500) NOT NULL,
                proposed_text VARCHAR(500) NOT NULL,
                comment TEXT NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'pending',
                reviewed_by INT UNSIGNED NULL,
                reviewed_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_ts_status_created (status, created_at),
                KEY idx_ts_user (user_id),
                KEY idx_ts_locale (locale)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
};
