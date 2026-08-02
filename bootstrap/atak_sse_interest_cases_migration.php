<?php

declare(strict_types=1);

/** Schéma initial des Dossiers d’intérêt (anciennement « pré-SSE »). */
return static function (PDO $pdo): void {
    $exists = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sse_interest_cases' LIMIT 1")->fetchColumn();
    if ($exists) {
        return;
    }

    $pdo->exec("CREATE TABLE sse_interest_cases (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        tenant_id INT UNSIGNED NOT NULL,
        reference_code VARCHAR(32) NOT NULL,
        temporary_designation VARCHAR(120) NOT NULL,
        suspected_alias VARCHAR(160) DEFAULT NULL,
        apparent_sex VARCHAR(32) DEFAULT NULL,
        estimated_age_range VARCHAR(64) DEFAULT NULL,
        suspected_nationality VARCHAR(100) DEFAULT NULL,
        suspected_affiliation VARCHAR(160) DEFAULT NULL,
        status VARCHAR(40) NOT NULL DEFAULT 'signalement_recu',
        confidence_level VARCHAR(24) NOT NULL DEFAULT 'non_evalue',
        interest_level VARCHAR(24) NOT NULL DEFAULT 'courant',
        opening_reason TEXT NOT NULL,
        origin_operator VARCHAR(160) DEFAULT NULL,
        observed_elements MEDIUMTEXT NULL,
        analysis_facts MEDIUMTEXT NULL,
        analysis_assumptions MEDIUMTEXT NULL,
        analysis_contradictions MEDIUMTEXT NULL,
        analysis_questions MEDIUMTEXT NULL,
        collection_needs MEDIUMTEXT NULL,
        operational_risk MEDIUMTEXT NULL,
        recommendations MEDIUMTEXT NULL,
        source_label VARCHAR(200) DEFAULT NULL,
        source_reliability VARCHAR(32) DEFAULT NULL,
        acquisition_at DATETIME DEFAULT NULL,
        mission_label VARCHAR(160) DEFAULT NULL,
        created_by INT UNSIGNED DEFAULT NULL,
        validated_by INT UNSIGNED DEFAULT NULL,
        validated_at DATETIME DEFAULT NULL,
        archived_at DATETIME DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_sse_interest_ref (tenant_id, reference_code),
        KEY idx_sse_interest_queue (tenant_id, status, interest_level),
        KEY idx_sse_interest_updated (tenant_id, updated_at),
        CONSTRAINT fk_sse_interest_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};
