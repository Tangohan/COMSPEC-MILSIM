<?php

declare(strict_types=1);

/**
 * Modules analytiques SSE :
 * - appréciations structurées (FAIT → … → HYPOTHÈSE)
 * - lacunes / besoins de renseignement
 * - registre des décisions (append-only)
 * - relations entre dossiers (parent, dérivé, connexe…)
 * - métadonnées bibliothèque : doctrine + type de fragment
 */
return static function (PDO $pdo): void {
    $tableExists = static function (PDO $pdo, string $table): bool {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
        $stmt->execute([$table]);

        return (bool) $stmt->fetchColumn();
    };
    $columnExists = static function (PDO $pdo, string $table, string $column): bool {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table, $column]);

        return (bool) $stmt->fetchColumn();
    };

    if (!$tableExists($pdo, 'sse_case_assessments')) {
        $pdo->exec("CREATE TABLE sse_case_assessments (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            case_id INT UNSIGNED NOT NULL,
            subject_label VARCHAR(200) NOT NULL DEFAULT '',
            fact_text MEDIUMTEXT NOT NULL,
            source_origin VARCHAR(40) NOT NULL DEFAULT 'observation',
            source_reliability CHAR(1) NOT NULL DEFAULT 'F',
            info_credibility TINYINT UNSIGNED NOT NULL DEFAULT 6,
            corroboration_text MEDIUMTEXT NULL,
            assessment_text MEDIUMTEXT NOT NULL,
            confidence VARCHAR(16) NOT NULL DEFAULT 'modere',
            confidence_justification MEDIUMTEXT NOT NULL,
            hypothesis_code VARCHAR(8) NOT NULL DEFAULT 'H1',
            hypothesis_text MEDIUMTEXT NULL,
            temporality VARCHAR(32) NOT NULL DEFAULT 'valable_a_date',
            temporality_date DATE DEFAULT NULL,
            urgency VARCHAR(32) DEFAULT NULL,
            divergence_code VARCHAR(40) DEFAULT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'active',
            version INT UNSIGNED NOT NULL DEFAULT 1,
            author_label VARCHAR(160) DEFAULT NULL,
            reviewer_label VARCHAR(160) DEFAULT NULL,
            validator_label VARCHAR(160) DEFAULT NULL,
            created_by INT UNSIGNED DEFAULT NULL,
            updated_by INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_sse_assess_case (tenant_id, case_id, status),
            KEY idx_sse_assess_conf (tenant_id, confidence)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    if (!$tableExists($pdo, 'sse_case_intel_gaps')) {
        $pdo->exec("CREATE TABLE sse_case_intel_gaps (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            case_id INT UNSIGNED NOT NULL,
            kind VARCHAR(24) NOT NULL DEFAULT 'lacune',
            title VARCHAR(220) NOT NULL,
            body MEDIUMTEXT NOT NULL,
            priority VARCHAR(16) NOT NULL DEFAULT 'normale',
            status VARCHAR(16) NOT NULL DEFAULT 'ouvert',
            linked_hypothesis VARCHAR(8) DEFAULT NULL,
            confirmation_criterion MEDIUMTEXT NULL,
            assignee_label VARCHAR(160) DEFAULT NULL,
            due_at DATE DEFAULT NULL,
            author_label VARCHAR(160) DEFAULT NULL,
            created_by INT UNSIGNED DEFAULT NULL,
            closed_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_sse_gap_case (tenant_id, case_id, status),
            KEY idx_sse_gap_prio (tenant_id, priority, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    if (!$tableExists($pdo, 'sse_case_analytical_decisions')) {
        $pdo->exec("CREATE TABLE sse_case_analytical_decisions (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            case_id INT UNSIGNED NOT NULL,
            decision_domain VARCHAR(40) NOT NULL,
            subject_label VARCHAR(220) NOT NULL DEFAULT '',
            value_before VARCHAR(160) DEFAULT NULL,
            value_after VARCHAR(160) NOT NULL,
            reason MEDIUMTEXT NOT NULL,
            assessment_id INT UNSIGNED DEFAULT NULL,
            author_label VARCHAR(160) DEFAULT NULL,
            decided_by INT UNSIGNED DEFAULT NULL,
            decided_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_sse_adec_case (tenant_id, case_id, decided_at),
            KEY idx_sse_adec_domain (tenant_id, decision_domain)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    if (!$tableExists($pdo, 'sse_case_links')) {
        $pdo->exec("CREATE TABLE sse_case_links (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            case_id INT UNSIGNED NOT NULL,
            related_case_id INT UNSIGNED NOT NULL,
            relation_type VARCHAR(24) NOT NULL DEFAULT 'connexe',
            note VARCHAR(500) DEFAULT NULL,
            former_reference VARCHAR(64) DEFAULT NULL,
            author_label VARCHAR(160) DEFAULT NULL,
            created_by INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_sse_case_link (tenant_id, case_id, related_case_id, relation_type),
            KEY idx_sse_case_link_rel (tenant_id, related_case_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    if ($tableExists($pdo, 'sse_text_templates')) {
        if (!$columnExists($pdo, 'sse_text_templates', 'doctrine')) {
            $pdo->exec("ALTER TABLE sse_text_templates
                ADD COLUMN doctrine VARCHAR(24) NOT NULL DEFAULT 'neutre' AFTER context");
        }
        if (!$columnExists($pdo, 'sse_text_templates', 'fragment_kind')) {
            $pdo->exec("ALTER TABLE sse_text_templates
                ADD COLUMN fragment_kind VARCHAR(16) NOT NULL DEFAULT 'bloc' AFTER doctrine");
        }
    }
};
