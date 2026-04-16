<?php

declare(strict_types=1);

/**
 * Colonnes catalogue militaire : semantic_tier + specialty, catégories, label_en, personnel_job_roles.label_en.
 * Idempotent — exécuter après roles_organic_architecture_migration.
 */
function run_military_role_catalog_schema_migration(PDO $pdo): void
{
    $hasColumn = static function (string $table, string $column) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    $hasTable = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if ($hasColumn('roles', 'semantic_tier')) {
        try {
            $pdo->exec(
                "ALTER TABLE roles MODIFY COLUMN semantic_tier ENUM('authority','function','specialty','status','support','liaison') NOT NULL DEFAULT 'function'"
            );
        } catch (Throwable $e) {
            echo '  [ATTENTION] roles.semantic_tier (specialty) : ' . $e->getMessage() . "\n";
        }
    }

    if (!$hasColumn('roles', 'category')) {
        try {
            $pdo->exec("ALTER TABLE roles ADD COLUMN category VARCHAR(100) NULL AFTER name");
        } catch (Throwable $e) {
            echo '  [ATTENTION] roles.category : ' . $e->getMessage() . "\n";
        }
    }
    if (!$hasColumn('roles', 'subcategory')) {
        try {
            $pdo->exec('ALTER TABLE roles ADD COLUMN subcategory VARCHAR(100) NULL AFTER category');
        } catch (Throwable $e) {
            echo '  [ATTENTION] roles.subcategory : ' . $e->getMessage() . "\n";
        }
    }
    if (!$hasColumn('roles', 'label_en')) {
        try {
            $pdo->exec('ALTER TABLE roles ADD COLUMN label_en VARCHAR(160) NULL AFTER subcategory');
        } catch (Throwable $e) {
            echo '  [ATTENTION] roles.label_en : ' . $e->getMessage() . "\n";
        }
    }

    if ($hasTable('personnel_job_roles') && !$hasColumn('personnel_job_roles', 'label_en')) {
        try {
            $pdo->exec('ALTER TABLE personnel_job_roles ADD COLUMN label_en VARCHAR(160) NULL AFTER description');
        } catch (Throwable $e) {
            echo '  [ATTENTION] personnel_job_roles.label_en : ' . $e->getMessage() . "\n";
        }
    }

    if ($hasTable('personnel_job_roles') && !$hasColumn('personnel_job_roles', 'mos_code')) {
        try {
            $pdo->exec('ALTER TABLE personnel_job_roles ADD COLUMN mos_code VARCHAR(16) NULL');
        } catch (Throwable $e) {
            echo '  [ATTENTION] personnel_job_roles.mos_code : ' . $e->getMessage() . "\n";
        }
    }
    if ($hasTable('personnel_job_roles') && !$hasColumn('personnel_job_roles', 'mos_specialty_title')) {
        try {
            $pdo->exec('ALTER TABLE personnel_job_roles ADD COLUMN mos_specialty_title VARCHAR(255) NULL');
        } catch (Throwable $e) {
            echo '  [ATTENTION] personnel_job_roles.mos_specialty_title : ' . $e->getMessage() . "\n";
        }
    }

    echo "  [OK] military_role_catalog_schema_migration\n";
}
