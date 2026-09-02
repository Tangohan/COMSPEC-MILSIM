<?php

declare(strict_types=1);

/**
 * Affectations multiples rôles métier par dossier personnel (pivot).
 * Idempotent.
 */
function run_personnel_profile_job_roles_migration(PDO $pdo): void
{
    $hasTable = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };
    $hasColumn = static function (string $table, string $column) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    if (!$hasTable('personnel_job_roles') || !$hasTable('users')) {
        return;
    }

    if (!$hasTable('personnel_profile_job_roles')) {
        try {
            $pdo->exec(
                'CREATE TABLE personnel_profile_job_roles (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    tenant_id INT UNSIGNED NOT NULL,
                    user_id INT UNSIGNED NOT NULL,
                    personnel_job_role_id INT UNSIGNED NOT NULL,
                    is_primary TINYINT(1) NOT NULL DEFAULT 0,
                    sort_order INT NOT NULL DEFAULT 0,
                    role_detail VARCHAR(150) DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uk_ppjr_tenant_user_role (tenant_id, user_id, personnel_job_role_id),
                    KEY idx_ppjr_tenant_user (tenant_id, user_id),
                    KEY idx_ppjr_jobrole (personnel_job_role_id),
                    CONSTRAINT ppjr_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT ppjr_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT ppjr_jobrole_fk FOREIGN KEY (personnel_job_role_id) REFERENCES personnel_job_roles (id) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (Throwable $e) {
            echo '  [ATTENTION] personnel_profile_job_roles : ' . $e->getMessage() . "\n";

            return;
        }
    }

    // Les premières versions de la table pivot ne stockaient pas la précision
    // saisie depuis le dossier. CREATE TABLE IF NOT EXISTS ne fait pas évoluer
    // ces installations : l'INSERT de sauvegarde échouait alors sur role_detail.
    if (!$hasColumn('personnel_profile_job_roles', 'role_detail')) {
        try {
            $pdo->exec(
                'ALTER TABLE personnel_profile_job_roles
                 ADD COLUMN role_detail VARCHAR(150) DEFAULT NULL AFTER sort_order'
            );
        } catch (Throwable $e) {
            echo '  [ATTENTION] personnel_profile_job_roles.role_detail : ' . $e->getMessage() . "\n";
        }
    }

    if ($hasTable('personnel_profiles') && $hasColumn('personnel_profiles', 'personnel_job_role_id')) {
        try {
            $pdo->exec(
                'INSERT IGNORE INTO personnel_profile_job_roles (tenant_id, user_id, personnel_job_role_id, is_primary, sort_order)
                 SELECT u.tenant_id, pp.user_id, pp.personnel_job_role_id, 1, 0
                 FROM personnel_profiles pp
                 INNER JOIN users u ON u.id = pp.user_id
                 WHERE pp.personnel_job_role_id IS NOT NULL AND pp.personnel_job_role_id > 0'
            );
        } catch (Throwable $e) {
            echo '  [ATTENTION] personnel_profile_job_roles backfill : ' . $e->getMessage() . "\n";
        }
    }

    echo "  [OK] personnel_profile_job_roles\n";
}
