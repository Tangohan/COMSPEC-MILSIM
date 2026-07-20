<?php

declare(strict_types=1);

/**
 * Tags partagés entre modules LMS (formations, Documentations HTML, extensible à d'autres
 * contenus). Table de tags + table de liaison polymorphe (content_type + content_id),
 * idempotent.
 */
return static function (PDO $pdo): void {
    $tableExists = static function (PDO $pdo, string $table): bool {
        $st = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if (!$tableExists($pdo, 'content_tags')) {
        $pdo->exec(
            "CREATE TABLE content_tags (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT UNSIGNED NOT NULL,
                name VARCHAR(60) NOT NULL,
                slug VARCHAR(80) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_content_tags_tenant_slug (tenant_id, slug),
                KEY idx_content_tags_tenant (tenant_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    if (!$tableExists($pdo, 'content_tag_links')) {
        $pdo->exec(
            "CREATE TABLE content_tag_links (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tag_id BIGINT UNSIGNED NOT NULL,
                content_type VARCHAR(30) NOT NULL,
                content_id BIGINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_ctl_tag_content (tag_id, content_type, content_id),
                KEY idx_ctl_content (content_type, content_id),
                CONSTRAINT fk_ctl_tag FOREIGN KEY (tag_id) REFERENCES content_tags (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }
};
