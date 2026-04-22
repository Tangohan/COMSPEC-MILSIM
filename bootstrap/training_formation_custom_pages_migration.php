<?php

declare(strict_types=1);

/**
 * Studio DOC HTML / Manuel — schéma complet multi-tenant, idempotent.
 */
return static function (PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS training_formation_custom_pages (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            slug VARCHAR(120) NOT NULL,
            title VARCHAR(255) NOT NULL,
            subtitle VARCHAR(255) NULL,
            summary TEXT NULL,
            doc_structure ENUM('single','handbook') NOT NULL DEFAULT 'single',
            intro_html MEDIUMTEXT NULL,
            html_body MEDIUMTEXT NOT NULL,
            sections_json LONGTEXT NULL,
            theme_id BIGINT UNSIGNED NULL,
            cover_image VARCHAR(255) NULL,
            icon VARCHAR(64) NULL,
            accent_color VARCHAR(16) NULL,
            layout_mode VARCHAR(24) NOT NULL DEFAULT 'standard',
            show_toc TINYINT(1) NOT NULL DEFAULT 1,
            show_reading_progress TINYINT(1) NOT NULL DEFAULT 1,
            visibility_level VARCHAR(32) NOT NULL DEFAULT 'tenant',
            allowed_roles_json LONGTEXT NULL,
            estimated_read_time SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            status ENUM('draft','review','scheduled','published','archived') NOT NULL DEFAULT 'draft',
            is_published TINYINT(1) NOT NULL DEFAULT 0,
            published_at DATETIME NULL,
            scheduled_publish_at DATETIME NULL,
            archived_at DATETIME NULL,
            last_published_by INT UNSIGNED NULL,
            last_edited_at DATETIME NULL,
            last_viewed_at DATETIME NULL,
            view_count INT UNSIGNED NOT NULL DEFAULT 0,
            canonical_url VARCHAR(255) NULL,
            meta_title VARCHAR(255) NULL,
            meta_description VARCHAR(320) NULL,
            og_image VARCHAR(255) NULL,
            created_by INT UNSIGNED NULL,
            updated_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_training_custom_page_slug (tenant_id, slug),
            KEY idx_training_custom_page_tenant (tenant_id),
            KEY idx_training_custom_page_status (tenant_id, status, updated_at),
            KEY idx_training_custom_page_schedule (tenant_id, scheduled_publish_at),
            CONSTRAINT fk_training_custom_page_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $alterStatements = [
        'ALTER TABLE training_formation_custom_pages ADD COLUMN subtitle VARCHAR(255) NULL AFTER title',
        'ALTER TABLE training_formation_custom_pages ADD COLUMN summary TEXT NULL AFTER subtitle',
        "ALTER TABLE training_formation_custom_pages ADD COLUMN doc_structure ENUM('single','handbook') NOT NULL DEFAULT 'single' AFTER summary",
        'ALTER TABLE training_formation_custom_pages ADD COLUMN intro_html MEDIUMTEXT NULL AFTER doc_structure',
        'ALTER TABLE training_formation_custom_pages ADD COLUMN theme_id BIGINT UNSIGNED NULL AFTER sections_json',
        'ALTER TABLE training_formation_custom_pages ADD COLUMN cover_image VARCHAR(255) NULL AFTER theme_id',
        'ALTER TABLE training_formation_custom_pages ADD COLUMN icon VARCHAR(64) NULL AFTER cover_image',
        'ALTER TABLE training_formation_custom_pages ADD COLUMN accent_color VARCHAR(16) NULL AFTER icon',
        "ALTER TABLE training_formation_custom_pages ADD COLUMN layout_mode VARCHAR(24) NOT NULL DEFAULT 'standard' AFTER accent_color",
        'ALTER TABLE training_formation_custom_pages ADD COLUMN show_toc TINYINT(1) NOT NULL DEFAULT 1 AFTER layout_mode',
        'ALTER TABLE training_formation_custom_pages ADD COLUMN show_reading_progress TINYINT(1) NOT NULL DEFAULT 1 AFTER show_toc',
        "ALTER TABLE training_formation_custom_pages ADD COLUMN visibility_level VARCHAR(32) NOT NULL DEFAULT 'tenant' AFTER show_reading_progress",
        'ALTER TABLE training_formation_custom_pages ADD COLUMN allowed_roles_json LONGTEXT NULL AFTER visibility_level',
        'ALTER TABLE training_formation_custom_pages ADD COLUMN estimated_read_time SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER allowed_roles_json',
        'ALTER TABLE training_formation_custom_pages ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER estimated_read_time',
        "ALTER TABLE training_formation_custom_pages ADD COLUMN status ENUM('draft','review','scheduled','published','archived') NOT NULL DEFAULT 'draft' AFTER sort_order",
        'ALTER TABLE training_formation_custom_pages ADD COLUMN published_at DATETIME NULL AFTER is_published',
        'ALTER TABLE training_formation_custom_pages ADD COLUMN scheduled_publish_at DATETIME NULL AFTER published_at',
        'ALTER TABLE training_formation_custom_pages ADD COLUMN archived_at DATETIME NULL AFTER scheduled_publish_at',
        'ALTER TABLE training_formation_custom_pages ADD COLUMN last_published_by INT UNSIGNED NULL AFTER updated_by',
        'ALTER TABLE training_formation_custom_pages ADD COLUMN last_edited_at DATETIME NULL AFTER last_published_by',
        'ALTER TABLE training_formation_custom_pages ADD COLUMN last_viewed_at DATETIME NULL AFTER last_edited_at',
        'ALTER TABLE training_formation_custom_pages ADD COLUMN view_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER last_viewed_at',
        'ALTER TABLE training_formation_custom_pages ADD COLUMN canonical_url VARCHAR(255) NULL AFTER view_count',
        'ALTER TABLE training_formation_custom_pages ADD COLUMN meta_title VARCHAR(255) NULL AFTER canonical_url',
        'ALTER TABLE training_formation_custom_pages ADD COLUMN meta_description VARCHAR(320) NULL AFTER meta_title',
        'ALTER TABLE training_formation_custom_pages ADD COLUMN og_image VARCHAR(255) NULL AFTER meta_description',
        'ALTER TABLE training_formation_custom_pages ADD COLUMN sections_json LONGTEXT NULL DEFAULT NULL AFTER html_body',
    ];

    foreach ($alterStatements as $sql) {
        try {
            $pdo->exec($sql);
        } catch (\PDOException $e) {
            $msg = strtolower($e->getMessage());
            if (!str_contains($msg, 'duplicate column') && !str_contains($msg, 'exists')) {
                throw $e;
            }
        }
    }

    try {
        $pdo->exec('CREATE INDEX idx_training_custom_page_status ON training_formation_custom_pages (tenant_id, status, updated_at)');
    } catch (\PDOException $e) {
        if (!str_contains(strtolower($e->getMessage()), 'duplicate')) {
            throw $e;
        }
    }
    try {
        $pdo->exec('CREATE INDEX idx_training_custom_page_schedule ON training_formation_custom_pages (tenant_id, scheduled_publish_at)');
    } catch (\PDOException $e) {
        if (!str_contains(strtolower($e->getMessage()), 'duplicate')) {
            throw $e;
        }
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS training_formation_custom_page_chapters (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            page_id BIGINT UNSIGNED NOT NULL,
            tenant_id INT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(120) NOT NULL,
            summary TEXT NULL,
            body_html MEDIUMTEXT NOT NULL,
            order_index INT NOT NULL DEFAULT 0,
            is_hidden TINYINT(1) NOT NULL DEFAULT 0,
            callout_variant VARCHAR(24) NULL,
            badge_label VARCHAR(64) NULL,
            icon VARCHAR(64) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_training_custom_page_chapter_slug (page_id, slug),
            KEY idx_training_custom_page_chapter_order (page_id, order_index),
            KEY idx_training_custom_page_chapter_tenant (tenant_id, page_id),
            CONSTRAINT fk_training_custom_page_chapter_page FOREIGN KEY (page_id) REFERENCES training_formation_custom_pages (id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_training_custom_page_chapter_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS training_formation_custom_page_revisions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            page_id BIGINT UNSIGNED NOT NULL,
            tenant_id INT UNSIGNED NOT NULL,
            version_no INT UNSIGNED NOT NULL,
            status_snapshot VARCHAR(32) NOT NULL,
            revision_type VARCHAR(24) NOT NULL DEFAULT 'manual',
            title VARCHAR(255) NOT NULL,
            content_snapshot_json LONGTEXT NOT NULL,
            summary_diff TEXT NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uq_training_custom_page_revision_no (page_id, version_no),
            KEY idx_training_custom_page_revision_tenant (tenant_id, page_id, created_at),
            CONSTRAINT fk_training_custom_page_revision_page FOREIGN KEY (page_id) REFERENCES training_formation_custom_pages (id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_training_custom_page_revision_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS training_formation_custom_page_themes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            name VARCHAR(120) NOT NULL,
            slug VARCHAR(120) NOT NULL,
            description VARCHAR(255) NULL,
            is_system TINYINT(1) NOT NULL DEFAULT 0,
            variant VARCHAR(32) NOT NULL DEFAULT 'guide',
            accent_color VARCHAR(16) NOT NULL DEFAULT '#0f766e',
            palette_json LONGTEXT NULL,
            config_json LONGTEXT NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_training_custom_page_theme_slug (tenant_id, slug),
            KEY idx_training_custom_page_theme_tenant (tenant_id, is_system),
            CONSTRAINT fk_training_custom_page_theme_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS training_formation_custom_page_templates (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            name VARCHAR(120) NOT NULL,
            slug VARCHAR(120) NOT NULL,
            doc_structure ENUM('single','handbook') NOT NULL DEFAULT 'single',
            description VARCHAR(255) NULL,
            template_json LONGTEXT NOT NULL,
            is_system TINYINT(1) NOT NULL DEFAULT 0,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_training_custom_page_template_slug (tenant_id, slug),
            KEY idx_training_custom_page_template_tenant (tenant_id, is_system),
            CONSTRAINT fk_training_custom_page_template_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS training_formation_custom_page_activity (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            page_id BIGINT UNSIGNED NOT NULL,
            tenant_id INT UNSIGNED NOT NULL,
            actor_user_id INT UNSIGNED NULL,
            action VARCHAR(48) NOT NULL,
            details_json LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            KEY idx_training_custom_page_activity (tenant_id, page_id, created_at),
            CONSTRAINT fk_training_custom_page_activity_page FOREIGN KEY (page_id) REFERENCES training_formation_custom_pages (id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_training_custom_page_activity_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS training_formation_custom_page_views (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            page_id BIGINT UNSIGNED NOT NULL,
            tenant_id INT UNSIGNED NOT NULL,
            viewer_user_id INT UNSIGNED NULL,
            viewed_at DATETIME NOT NULL,
            reading_seconds INT UNSIGNED NULL,
            completed_ratio DECIMAL(5,2) NULL,
            KEY idx_training_custom_page_views (tenant_id, page_id, viewed_at),
            CONSTRAINT fk_training_custom_page_views_page FOREIGN KEY (page_id) REFERENCES training_formation_custom_pages (id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_training_custom_page_views_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $tenantStmt = $pdo->query('SELECT id FROM tenants');
    $tenantIds = $tenantStmt ? ($tenantStmt->fetchAll(PDO::FETCH_COLUMN) ?: []) : [];
    $defaultThemes = [
        ['Doctrine institutionnelle', 'doctrine', 'doctrine', '#0b4a6f'],
        ['Manuel opérationnel', 'manuel-operationnel', 'manual', '#14532d'],
        ['Fiche réflexe', 'fiche-reflexe', 'sop', '#7f1d1d'],
    ];
    $defaultTemplates = [
        ['Guide standard', 'guide-standard', 'single'],
        ['Manuel à chapitres', 'manuel-chapitres', 'handbook'],
    ];

    foreach ($tenantIds as $tenantIdRaw) {
        $tenantId = (int) $tenantIdRaw;
        foreach ($defaultThemes as [$name, $slug, $variant, $accent]) {
            $stmt = $pdo->prepare('SELECT id FROM training_formation_custom_page_themes WHERE tenant_id = ? AND slug = ? LIMIT 1');
            $stmt->execute([$tenantId, $slug]);
            if ($stmt->fetchColumn()) {
                continue;
            }
            $ins = $pdo->prepare('INSERT INTO training_formation_custom_page_themes (tenant_id, name, slug, description, is_system, variant, accent_color, palette_json, config_json, created_at, updated_at) VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?, NOW(), NOW())');
            $ins->execute([$tenantId, $name, $slug, 'Thème système DOC HTML', $variant, $accent, json_encode(['accent' => $accent], JSON_UNESCAPED_UNICODE), json_encode(['layout' => 'standard'], JSON_UNESCAPED_UNICODE)]);
        }
        foreach ($defaultTemplates as [$name, $slug, $structure]) {
            $stmt = $pdo->prepare('SELECT id FROM training_formation_custom_page_templates WHERE tenant_id = ? AND slug = ? LIMIT 1');
            $stmt->execute([$tenantId, $slug]);
            if ($stmt->fetchColumn()) {
                continue;
            }
            $payload = $structure === 'handbook'
                ? ['title' => $name, 'intro_html' => '<p>Introduction.</p>', 'chapters' => [['title' => 'Chapitre 1', 'slug' => 'chapitre-1', 'html' => '<p>Contenu.</p>']]]
                : ['title' => $name, 'body_html' => '<h2>Contexte</h2><p>Rédigez votre contenu.</p>'];
            $ins = $pdo->prepare('INSERT INTO training_formation_custom_page_templates (tenant_id, name, slug, doc_structure, description, template_json, is_system, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW())');
            $ins->execute([$tenantId, $name, $slug, $structure, 'Template système DOC HTML', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        }
    }
};
