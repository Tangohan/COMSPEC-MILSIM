<?php

declare(strict_types=1);

/**
 * Raccourcis épinglés dashboard (périmètre tenant).
 * Idempotent — appelée depuis run-migrations.php.
 */
return function (PDO $pdo): void {
    $chk = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_dashboard_pins' LIMIT 1");
    if ($chk && $chk->fetch()) {
        return;
    }

    $pdo->exec(
        'CREATE TABLE tenant_dashboard_pins (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            pin_type ENUM(\'document_category\',\'document\',\'courrier_document\',\'external_url\',\'notice\') NOT NULL,
            document_category_id INT UNSIGNED DEFAULT NULL,
            document_id INT UNSIGNED DEFAULT NULL,
            courrier_document_id INT UNSIGNED DEFAULT NULL,
            external_url VARCHAR(2000) DEFAULT NULL,
            title VARCHAR(500) DEFAULT NULL,
            notice_body MEDIUMTEXT DEFAULT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_by INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_tdp_tenant_sort (tenant_id, sort_order),
            CONSTRAINT tdp_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
            CONSTRAINT tdp_doc_cat_fk FOREIGN KEY (document_category_id) REFERENCES document_categories (id) ON DELETE CASCADE,
            CONSTRAINT tdp_document_fk FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE,
            CONSTRAINT tdp_courrier_fk FOREIGN KEY (courrier_document_id) REFERENCES courrier_documents (id) ON DELETE CASCADE,
            CONSTRAINT tdp_created_by_fk FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    echo "Table tenant_dashboard_pins créée.\n";
};
