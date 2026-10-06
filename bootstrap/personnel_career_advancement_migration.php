<?php

declare(strict_types=1);

/**
 * Décorations (award_definitions / personnel_awards) et dotation nominative.
 * L’échelle de grades et les campagnes d’avancement sont gérées par
 * bootstrap/advancement_grade_migration.php.
 * Idempotent — jamais de suppression physique d’historique.
 */
function run_personnel_career_advancement_migration(PDO $pdo): void
{
    $hasTable = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };
    require_once __DIR__ . '/migration_permissions.php';
    $ensurePermission = static function (string $code, string $name, string $description) use ($pdo): void {
        migration_ensure_global_permission($pdo, $code, $name, $description, 'personnel');
    };

    if (!$hasTable('award_definitions')) {
        $pdo->exec(
            "CREATE TABLE award_definitions (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                code VARCHAR(40) NOT NULL,
                name VARCHAR(180) NOT NULL,
                decoration_grade VARCHAR(80) DEFAULT NULL,
                award_criterion TEXT DEFAULT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                archived_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_by INT UNSIGNED DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_award_def_tenant_code (tenant_id, code),
                KEY idx_award_def_tenant (tenant_id, sort_order),
                CONSTRAINT award_def_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // Insigne (PNG / WebP / JPEG) et branche / arme de la décoration — ajout idempotent.
    if ($hasTable('award_definitions')) {
        $hasColumn = static function (string $column) use ($pdo): bool {
            $st = $pdo->prepare(
                'SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
            );
            $st->execute(['award_definitions', $column]);

            return (bool) $st->fetchColumn();
        };
        if (!$hasColumn('branch')) {
            $pdo->exec('ALTER TABLE award_definitions ADD COLUMN branch VARCHAR(80) DEFAULT NULL AFTER name');
        }
        if (!$hasColumn('image_path')) {
            $pdo->exec('ALTER TABLE award_definitions ADD COLUMN image_path VARCHAR(255) DEFAULT NULL AFTER award_criterion');
        }
    }

    if (!$hasTable('personnel_awards')) {
        $pdo->exec(
            "CREATE TABLE personnel_awards (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                personnel_id INT UNSIGNED NOT NULL,
                definition_id INT UNSIGNED NOT NULL,
                citation_text TEXT DEFAULT NULL,
                authority VARCHAR(180) DEFAULT NULL,
                awarded_at DATE NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_by INT UNSIGNED DEFAULT NULL,
                PRIMARY KEY (id),
                KEY idx_personnel_awards_user (tenant_id, personnel_id, awarded_at),
                KEY idx_personnel_awards_def (definition_id),
                CONSTRAINT paward_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT paward_user_fk FOREIGN KEY (personnel_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT paward_def_fk FOREIGN KEY (definition_id) REFERENCES award_definitions (id) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('equipment_item_definitions')) {
        $pdo->exec(
            "CREATE TABLE equipment_item_definitions (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                code VARCHAR(40) NOT NULL,
                name VARCHAR(180) NOT NULL,
                category VARCHAR(80) DEFAULT NULL,
                description TEXT DEFAULT NULL,
                archived_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_by INT UNSIGNED DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_equip_def_tenant_code (tenant_id, code),
                KEY idx_equip_def_tenant (tenant_id, name),
                CONSTRAINT equip_def_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('personnel_equipment_assignments')) {
        $pdo->exec(
            "CREATE TABLE personnel_equipment_assignments (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                personnel_id INT UNSIGNED NOT NULL,
                definition_id INT UNSIGNED NOT NULL,
                serial_number VARCHAR(80) DEFAULT NULL,
                status VARCHAR(24) NOT NULL DEFAULT 'issued',
                assigned_at DATE NOT NULL,
                returned_at DATE DEFAULT NULL,
                notes TEXT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_by INT UNSIGNED DEFAULT NULL,
                PRIMARY KEY (id),
                KEY idx_equip_asg_user (tenant_id, personnel_id, status),
                KEY idx_equip_asg_serial (tenant_id, serial_number),
                CONSTRAINT equip_asg_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT equip_asg_user_fk FOREIGN KEY (personnel_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT equip_asg_def_fk FOREIGN KEY (definition_id) REFERENCES equipment_item_definitions (id) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('personnel_equipment_assignment_history')) {
        $pdo->exec(
            "CREATE TABLE personnel_equipment_assignment_history (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                assignment_id BIGINT UNSIGNED NOT NULL,
                event VARCHAR(32) NOT NULL,
                from_status VARCHAR(24) DEFAULT NULL,
                to_status VARCHAR(24) DEFAULT NULL,
                personnel_id INT UNSIGNED DEFAULT NULL,
                notes VARCHAR(500) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_by INT UNSIGNED DEFAULT NULL,
                PRIMARY KEY (id),
                KEY idx_equip_hist_asg (assignment_id, created_at),
                CONSTRAINT equip_hist_asg_fk FOREIGN KEY (assignment_id) REFERENCES personnel_equipment_assignments (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    $ensurePermission(
        'personnel.awards.manage',
        'Gérer les décorations',
        'Référentiel et attributions de décorations / citations.'
    );
    $ensurePermission(
        'personnel.equipment.manage',
        'Gérer la dotation',
        'Référentiel matériel et attributions nominatives.'
    );

    echo "  [OK] personnel_career_advancement (décorations, dotation nominative)\n";
}
