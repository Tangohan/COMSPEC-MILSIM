<?php

declare(strict_types=1);

/**
 * Charte RH (LMS) : document publié par communauté + prise en compte par membre.
 * Idempotent.
 */
function run_hr_charter_lms_migration(PDO $pdo): void
{
    $hasTable = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if (!$hasTable('lms_hr_charter_documents')) {
        $pdo->exec(
            "CREATE TABLE lms_hr_charter_documents (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                title VARCHAR(255) NOT NULL,
                body_html MEDIUMTEXT NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                published_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_lms_hr_charter_tenant_active (tenant_id, is_active, published_at),
                CONSTRAINT lms_hr_charter_documents_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('lms_hr_charter_acceptances')) {
        $pdo->exec(
            "CREATE TABLE lms_hr_charter_acceptances (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                document_id INT UNSIGNED NOT NULL,
                accepted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                ip_address VARCHAR(64) DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_lms_hr_charter_user_doc (user_id, document_id),
                KEY idx_lms_hr_charter_accept_user (user_id, tenant_id),
                CONSTRAINT lms_hr_charter_accept_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT lms_hr_charter_accept_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT lms_hr_charter_accept_doc_fk FOREIGN KEY (document_id) REFERENCES lms_hr_charter_documents (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    echo "  [OK] hr_charter_lms (documents, acceptances)\n";
}
