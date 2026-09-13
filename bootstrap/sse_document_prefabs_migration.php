<?php

declare(strict_types=1);

/**
 * Prefabs de présentation des documents / fiches SSE (chrome + style papier).
 */
return static function (PDO $pdo): void {
    $tableExists = static function (PDO $pdo, string $table): bool {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
        $stmt->execute([$table]);

        return (bool) $stmt->fetchColumn();
    };

    if (!$tableExists($pdo, 'sse_document_prefabs')) {
        $pdo->exec("CREATE TABLE sse_document_prefabs (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            code VARCHAR(48) NOT NULL,
            label VARCHAR(160) NOT NULL,
            description VARCHAR(400) DEFAULT NULL,
            paper_style VARCHAR(24) NOT NULL DEFAULT 'clean',
            banner VARCHAR(220) NOT NULL,
            title_person VARCHAR(120) NOT NULL,
            title_docs VARCHAR(120) NOT NULL,
            subtitle_dossier VARCHAR(180) NOT NULL,
            subtitle_feuille VARCHAR(180) NOT NULL,
            subtitle_docs VARCHAR(180) NOT NULL,
            footer VARCHAR(400) NOT NULL,
            quality_prefix VARCHAR(80) NOT NULL DEFAULT 'Qualité d’exploitation',
            org_line VARCHAR(120) NOT NULL DEFAULT 'ATHENA · COMPSEC',
            seal_top VARCHAR(80) NOT NULL DEFAULT 'BUREAU SSE',
            seal_bottom VARCHAR(80) NOT NULL DEFAULT 'RENSEIGNEMENT',
            access_note VARCHAR(400) DEFAULT NULL,
            btn_consult VARCHAR(40) NOT NULL DEFAULT 'FEUILLE',
            btn_transmit VARCHAR(40) NOT NULL DEFAULT 'TRANSMETTRE',
            btn_close VARCHAR(40) NOT NULL DEFAULT 'FERMER',
            is_builtin TINYINT(1) NOT NULL DEFAULT 0,
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 100,
            created_by INT UNSIGNED DEFAULT NULL,
            updated_by INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_sse_doc_prefab (tenant_id, code),
            KEY idx_sse_doc_prefab_active (tenant_id, is_active, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    if (!$tableExists($pdo, 'sse_document_chrome_settings')) {
        $pdo->exec("CREATE TABLE sse_document_chrome_settings (
            tenant_id INT UNSIGNED NOT NULL,
            active_prefab_code VARCHAR(48) NOT NULL DEFAULT 'standard_restreint',
            updated_by INT UNSIGNED DEFAULT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (tenant_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
};
