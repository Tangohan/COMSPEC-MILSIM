<?php

declare(strict_types=1);

/**
 * Avis lecteurs (note + commentaire court) sur les Documentations HTML — pendant du feedback
 * post-leçon des formations, idempotent.
 */
return static function (PDO $pdo): void {
    $chk = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_formation_custom_page_feedback' LIMIT 1");
    if ($chk && $chk->fetchColumn()) {
        return;
    }
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS training_formation_custom_page_feedback (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            page_id BIGINT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            rating TINYINT UNSIGNED NOT NULL,
            comment TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_tfcpf_page_user (page_id, user_id),
            KEY idx_tfcpf_tenant (tenant_id),
            KEY idx_tfcpf_page (page_id),
            CONSTRAINT fk_tfcpf_page FOREIGN KEY (page_id) REFERENCES training_formation_custom_pages (id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
};
