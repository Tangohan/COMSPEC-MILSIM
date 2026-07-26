<?php

declare(strict_types=1);

/**
 * Couche Reels / feed vertical sur community_media_* (fusion stockage + social).
 *
 * - Étend community_media_items (origin, compteurs, modération, miniatures, variants)
 * - Crée community_media_comments, community_user_follows, community_media_reports
 * - Backfill likes_count / published_at / author_user_id
 *
 * Idempotent — appelée depuis run-migrations.php et migrate-community-reels.php.
 *
 * @return callable(PDO): void
 */
return static function (PDO $pdo): void {
    $hasTable = static function (PDO $pdo, string $table): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    $hasColumn = static function (PDO $pdo, string $table, string $column): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    $hasIndex = static function (PDO $pdo, string $table, string $indexName): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $indexName]);

        return (bool) $st->fetchColumn();
    };

    $addColumn = static function (
        PDO $pdo,
        string $table,
        string $column,
        string $ddl,
        callable $hasColumn
    ): void {
        if ($hasColumn($pdo, $table, $column)) {
            echo "  [SKIP] {$table}.{$column} déjà présent\n";

            return;
        }
        try {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN {$ddl}");
            echo "  [OK] {$table}.{$column}\n";
        } catch (Throwable $e) {
            echo "  [ATTENTION] {$table}.{$column} : " . $e->getMessage() . "\n";
        }
    };

    if (!$hasTable($pdo, 'community_media_items')) {
        echo "  [ATTENTION] community_media_items absente — exécutez d'abord la migration community_media\n";

        return;
    }

    echo "Extension community_media_items (reels)...\n";

    $addColumn(
        $pdo,
        'community_media_items',
        'origin',
        "origin VARCHAR(32) NOT NULL DEFAULT 'staff' COMMENT 'staff|member' AFTER created_by",
        $hasColumn
    );
    $addColumn(
        $pdo,
        'community_media_items',
        'author_user_id',
        'author_user_id INT UNSIGNED NULL AFTER origin',
        $hasColumn
    );
    $addColumn(
        $pdo,
        'community_media_items',
        'visibility',
        "visibility VARCHAR(32) NOT NULL DEFAULT 'public' COMMENT 'public|private|followers' AFTER author_user_id",
        $hasColumn
    );
    $addColumn(
        $pdo,
        'community_media_items',
        'moderation_status',
        "moderation_status VARCHAR(32) NOT NULL DEFAULT 'approved' COMMENT 'pending_review|approved|rejected|removed' AFTER visibility",
        $hasColumn
    );
    $addColumn(
        $pdo,
        'community_media_items',
        'published_at',
        'published_at DATETIME NULL AFTER moderation_status',
        $hasColumn
    );
    $addColumn(
        $pdo,
        'community_media_items',
        'thumbnail_path',
        'thumbnail_path VARCHAR(512) NULL AFTER published_at',
        $hasColumn
    );
    $addColumn(
        $pdo,
        'community_media_items',
        'poster_path',
        'poster_path VARCHAR(512) NULL AFTER thumbnail_path',
        $hasColumn
    );
    $addColumn(
        $pdo,
        'community_media_items',
        'variants_json',
        'variants_json JSON NULL AFTER poster_path',
        $hasColumn
    );
    $addColumn(
        $pdo,
        'community_media_items',
        'views_count',
        'views_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER variants_json',
        $hasColumn
    );
    $addColumn(
        $pdo,
        'community_media_items',
        'likes_count',
        'likes_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER views_count',
        $hasColumn
    );
    $addColumn(
        $pdo,
        'community_media_items',
        'comments_count',
        'comments_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER likes_count',
        $hasColumn
    );
    $addColumn(
        $pdo,
        'community_media_items',
        'shares_count',
        'shares_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER comments_count',
        $hasColumn
    );

    if (!$hasIndex($pdo, 'community_media_items', 'idx_cmi_reel_feed')) {
        try {
            $pdo->exec(
                'ALTER TABLE community_media_items
                 ADD INDEX idx_cmi_reel_feed (
                    tenant_id, media_kind, moderation_status, visibility, published_at, id
                 )'
            );
            echo "  [OK] index idx_cmi_reel_feed\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] idx_cmi_reel_feed : ' . $e->getMessage() . "\n";
        }
    } else {
        echo "  [SKIP] index idx_cmi_reel_feed déjà présent\n";
    }

    if (!$hasIndex($pdo, 'community_media_items', 'idx_cmi_author')) {
        try {
            $pdo->exec(
                'ALTER TABLE community_media_items
                 ADD INDEX idx_cmi_author (tenant_id, author_user_id, published_at, id)'
            );
            echo "  [OK] index idx_cmi_author\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] idx_cmi_author : ' . $e->getMessage() . "\n";
        }
    } else {
        echo "  [SKIP] index idx_cmi_author déjà présent\n";
    }

    echo "Backfill community_media_items...\n";
    try {
        $n = $pdo->exec(
            'UPDATE community_media_items
             SET author_user_id = COALESCE(author_user_id, created_by)
             WHERE author_user_id IS NULL AND created_by IS NOT NULL'
        );
        echo '  [OK] author_user_id backfill (' . (int) $n . " lignes)\n";
    } catch (Throwable $e) {
        echo '  [ATTENTION] author_user_id backfill : ' . $e->getMessage() . "\n";
    }

    try {
        $n = $pdo->exec(
            'UPDATE community_media_items
             SET published_at = COALESCE(published_at, created_at)
             WHERE published_at IS NULL AND show_on_public_page = 1'
        );
        echo '  [OK] published_at backfill (' . (int) $n . " lignes)\n";
    } catch (Throwable $e) {
        echo '  [ATTENTION] published_at backfill : ' . $e->getMessage() . "\n";
    }

    if ($hasTable($pdo, 'community_media_likes') && $hasColumn($pdo, 'community_media_items', 'likes_count')) {
        try {
            $n = $pdo->exec(
                'UPDATE community_media_items i
                 SET likes_count = (
                    SELECT COUNT(*) FROM community_media_likes l WHERE l.media_item_id = i.id
                 )'
            );
            echo '  [OK] likes_count recalculé (' . (int) $n . " lignes)\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] likes_count backfill : ' . $e->getMessage() . "\n";
        }
    }

    if (!$hasTable($pdo, 'community_media_comments')) {
        try {
            $pdo->exec(
                "CREATE TABLE community_media_comments (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    tenant_id INT UNSIGNED NOT NULL,
                    media_item_id BIGINT UNSIGNED NOT NULL,
                    user_id INT UNSIGNED NOT NULL,
                    parent_id BIGINT UNSIGNED NULL,
                    content TEXT NOT NULL,
                    status VARCHAR(32) NOT NULL DEFAULT 'visible',
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_cmco_item_cursor (media_item_id, status, id),
                    INDEX idx_cmco_parent (parent_id),
                    INDEX idx_cmco_tenant_user (tenant_id, user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            echo "  [OK] community_media_comments\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] community_media_comments : ' . $e->getMessage() . "\n";
        }
    } else {
        echo "  [SKIP] community_media_comments déjà présente\n";
    }

    if (!$hasTable($pdo, 'community_user_follows')) {
        try {
            $pdo->exec(
                'CREATE TABLE community_user_follows (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    tenant_id INT UNSIGNED NOT NULL,
                    follower_id INT UNSIGNED NOT NULL,
                    followed_id INT UNSIGNED NOT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_cuf (tenant_id, follower_id, followed_id),
                    INDEX idx_cuf_followed (tenant_id, followed_id),
                    INDEX idx_cuf_follower (tenant_id, follower_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            echo "  [OK] community_user_follows\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] community_user_follows : ' . $e->getMessage() . "\n";
        }
    } else {
        echo "  [SKIP] community_user_follows déjà présente\n";
    }

    if (!$hasTable($pdo, 'community_media_reports')) {
        try {
            $pdo->exec(
                "CREATE TABLE community_media_reports (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    tenant_id INT UNSIGNED NOT NULL,
                    media_item_id BIGINT UNSIGNED NOT NULL,
                    reporter_user_id INT UNSIGNED NOT NULL,
                    reason VARCHAR(64) NOT NULL,
                    details TEXT NULL,
                    status VARCHAR(32) NOT NULL DEFAULT 'pending',
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    reviewed_at DATETIME NULL,
                    reviewed_by INT UNSIGNED NULL,
                    UNIQUE KEY uq_cmr_once (reporter_user_id, media_item_id),
                    INDEX idx_cmr_queue (tenant_id, status, created_at),
                    INDEX idx_cmr_item (media_item_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            echo "  [OK] community_media_reports\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] community_media_reports : ' . $e->getMessage() . "\n";
        }
    } else {
        echo "  [SKIP] community_media_reports déjà présente\n";
    }

    echo "Migration community_media reels terminée.\n";
};
