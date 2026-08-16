<?php

declare(strict_types=1);

/**
 * Décisions humaines sur les rapprochements proposés (dossiers d'intérêt).
 *
 * Une proposition automatique n'a pas de valeur tant qu'un opérateur ne s'est pas
 * prononcé : la décision, son auteur et sa justification vivent ici.
 */
return static function (PDO $pdo): void {
    $exists = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sse_cross_decisions' LIMIT 1")->fetchColumn();
    if ($exists) {
        return;
    }

    $pdo->exec("CREATE TABLE sse_cross_decisions (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        tenant_id INT UNSIGNED NOT NULL,
        interest_case_id INT UNSIGNED NOT NULL,
        person_id INT UNSIGNED NOT NULL,
        entry_id INT UNSIGNED NOT NULL,
        decision VARCHAR(24) NOT NULL DEFAULT 'complement',
        score TINYINT UNSIGNED NOT NULL DEFAULT 0,
        reason VARCHAR(255) DEFAULT NULL,
        note VARCHAR(255) DEFAULT NULL,
        author_label VARCHAR(160) DEFAULT NULL,
        decided_by INT UNSIGNED DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_sse_cross_decision (tenant_id, interest_case_id, person_id, entry_id),
        KEY idx_sse_cross_decision_case (tenant_id, interest_case_id),
        CONSTRAINT fk_sse_cross_decision_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};
