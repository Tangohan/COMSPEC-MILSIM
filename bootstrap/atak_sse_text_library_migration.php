<?php

declare(strict_types=1);

/**
 * Bibliothèque rédactionnelle SSE.
 *
 * `sse_text_templates` : les mentions administrables par l'unité.
 * `sse_text_template_uses` : ce qui a réellement été porté au dossier, avec la version
 * du modèle au moment de l'insertion. Modifier un modèle central ne doit jamais
 * réécrire rétroactivement un document déjà rédigé.
 */
return static function (PDO $pdo): void {
    $tableExists = static function (PDO $pdo, string $table): bool {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
        $stmt->execute([$table]);

        return (bool) $stmt->fetchColumn();
    };

    if (!$tableExists($pdo, 'sse_text_templates')) {
        $pdo->exec("CREATE TABLE sse_text_templates (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            code VARCHAR(32) NOT NULL,
            category VARCHAR(32) NOT NULL DEFAULT 'mentions',
            title VARCHAR(180) NOT NULL,
            content MEDIUMTEXT NOT NULL,
            classification_min VARCHAR(32) DEFAULT NULL,
            context VARCHAR(24) NOT NULL DEFAULT 'dossier',
            variables VARCHAR(512) DEFAULT NULL,
            version INT UNSIGNED NOT NULL DEFAULT 1,
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            is_seeded TINYINT(1) NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 100,
            usage_count INT UNSIGNED NOT NULL DEFAULT 0,
            created_by INT UNSIGNED DEFAULT NULL,
            updated_by INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_sse_text_template (tenant_id, code),
            KEY idx_sse_text_template_cat (tenant_id, category, sort_order),
            KEY idx_sse_text_template_ctx (tenant_id, context, is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    if (!$tableExists($pdo, 'sse_text_template_uses')) {
        $pdo->exec("CREATE TABLE sse_text_template_uses (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            document_id INT UNSIGNED DEFAULT NULL,
            case_id INT UNSIGNED DEFAULT NULL,
            template_id INT UNSIGNED DEFAULT NULL,
            template_code VARCHAR(32) NOT NULL,
            template_version INT UNSIGNED NOT NULL DEFAULT 1,
            template_content MEDIUMTEXT NULL,
            inserted_text MEDIUMTEXT NULL,
            author_label VARCHAR(160) DEFAULT NULL,
            created_by INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_sse_text_use_doc (tenant_id, document_id),
            KEY idx_sse_text_use_code (tenant_id, template_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
};
