<?php

declare(strict_types=1);

return function (PDO $pdo): void {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS training_trainer_roles (
            tenant_id INT UNSIGNED NOT NULL,
            role_id INT UNSIGNED NOT NULL,
            created_by_user_id INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (tenant_id, role_id),
            KEY idx_ttr_role (role_id),
            CONSTRAINT fk_ttr_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_ttr_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_ttr_user FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (\PDOException $e) {
        echo '  [ATTENTION] training_trainer_roles : ' . $e->getMessage() . "\n";
    }

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS training_competency_matrices (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            name VARCHAR(120) NOT NULL,
            description TEXT NULL,
            auto_detect_rules_json JSON NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by_user_id INT UNSIGNED NULL,
            updated_by_user_id INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_tcm_tenant (tenant_id),
            CONSTRAINT fk_tcm_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_tcm_created_by FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
            CONSTRAINT fk_tcm_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (\PDOException $e) {
        echo '  [ATTENTION] training_competency_matrices : ' . $e->getMessage() . "\n";
    }

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS training_competency_matrix_assignments (
            tenant_id INT UNSIGNED NOT NULL,
            matrix_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            assigned_by_user_id INT UNSIGNED NULL,
            source ENUM('manual','auto_detect') NOT NULL DEFAULT 'manual',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (matrix_id, user_id),
            KEY idx_tcma_tenant_user (tenant_id, user_id),
            CONSTRAINT fk_tcma_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_tcma_matrix FOREIGN KEY (matrix_id) REFERENCES training_competency_matrices (id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_tcma_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_tcma_assigned_by FOREIGN KEY (assigned_by_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (\PDOException $e) {
        echo '  [ATTENTION] training_competency_matrix_assignments : ' . $e->getMessage() . "\n";
    }
};
