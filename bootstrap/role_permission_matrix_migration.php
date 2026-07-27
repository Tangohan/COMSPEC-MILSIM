<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $hasColumn = static function (string $table, string $column) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    $tableExists = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if (!$hasColumn('roles', 'role_code')) {
        try {
            $pdo->exec('ALTER TABLE roles ADD COLUMN role_code VARCHAR(16) DEFAULT NULL AFTER slug');
            $pdo->exec('ALTER TABLE roles ADD KEY idx_roles_tenant_code (tenant_id, role_code)');
        } catch (\Throwable) {
        }
    }

    if (!$hasColumn('roles', 'last_reviewed_at')) {
        try {
            $pdo->exec('ALTER TABLE roles ADD COLUMN last_reviewed_at DATETIME DEFAULT NULL');
        } catch (\Throwable) {
        }
    }

    if (!$hasColumn('roles', 'is_active')) {
        try {
            $pdo->exec('ALTER TABLE roles ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER is_locked');
        } catch (\Throwable) {
        }
    }

    if (!$tableExists('role_module_access')) {
        $pdo->exec(
            'CREATE TABLE role_module_access (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                role_id INT UNSIGNED NOT NULL,
                module_key VARCHAR(32) NOT NULL,
                access_level VARCHAR(32) NOT NULL DEFAULT \'none\',
                can_delete TINYINT(1) NOT NULL DEFAULT 0,
                can_export TINYINT(1) NOT NULL DEFAULT 0,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_role_module_access (tenant_id, role_id, module_key),
                KEY idx_role_module_access_tenant (tenant_id, module_key),
                CONSTRAINT fk_role_module_access_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_role_module_access_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
};
