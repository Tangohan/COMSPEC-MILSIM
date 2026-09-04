<?php

declare(strict_types=1);

/**
 * Images d’accueil après connexion (sas /login/accueil), par communauté. Idempotent.
 */
return static function (PDO $pdo): void {
    $tableExists = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if ($tableExists('tenant_login_accueil_images')) {
        return;
    }
    if (!$tableExists('tenants') || !$tableExists('users')) {
        return;
    }

    $pdo->exec(
        'CREATE TABLE tenant_login_accueil_images (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            storage_path VARCHAR(512) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            alt_text VARCHAR(200) NULL DEFAULT NULL,
            created_by INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_tlai_tenant_sort (tenant_id, sort_order),
            CONSTRAINT tlai_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
            CONSTRAINT tlai_created_by_fk FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    echo "Table tenant_login_accueil_images créée.\n";
};
