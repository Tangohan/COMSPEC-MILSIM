<?php

declare(strict_types=1);

/**
 * Grades sur mesure : une communauté peut créer son propre système de grades (SCP, Star Wars, Warhammer 40k…),
 * avec ses catégories et ses grades. Ils vivent dans les tables du référentiel (grade_systems, grade_categories,
 * grades) : les fiches membres, l’ORBAT, l’ATAK et l’avancement les lisent sans autre changement.
 *
 * - grade_systems.owner_tenant_id : NULL = catalogue partagé, sinon la communauté propriétaire ;
 * - grade_categories.owner_tenant_id / grade_system_id : catégorie propre à un système sur mesure.
 *
 * Idempotent — appelée depuis run-migrations.php.
 */
return static function (PDO $pdo): void {
    $hasTable = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };
    $hasColumn = static function (string $table, string $column) use ($pdo): bool {
        $st = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1');
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };
    $hasIndex = static function (string $table, string $index) use ($pdo): bool {
        $st = $pdo->prepare('SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1');
        $st->execute([$table, $index]);

        return (bool) $st->fetchColumn();
    };

    if (!$hasTable('grade_systems') || !$hasTable('grade_categories')) {
        return;
    }
    $changed = [];

    if (!$hasColumn('grade_systems', 'owner_tenant_id')) {
        $pdo->exec('ALTER TABLE grade_systems ADD COLUMN owner_tenant_id INT UNSIGNED NULL DEFAULT NULL AFTER format_type');
        $changed[] = 'grade_systems.owner_tenant_id';
    }
    if (!$hasIndex('grade_systems', 'idx_grade_systems_owner')) {
        $pdo->exec('ALTER TABLE grade_systems ADD KEY idx_grade_systems_owner (owner_tenant_id)');
    }
    if (!$hasColumn('grade_categories', 'owner_tenant_id')) {
        $pdo->exec('ALTER TABLE grade_categories ADD COLUMN owner_tenant_id INT UNSIGNED NULL DEFAULT NULL AFTER sort_order');
        $changed[] = 'grade_categories.owner_tenant_id';
    }
    if (!$hasColumn('grade_categories', 'grade_system_id')) {
        $pdo->exec('ALTER TABLE grade_categories ADD COLUMN grade_system_id BIGINT UNSIGNED NULL DEFAULT NULL AFTER owner_tenant_id');
        $changed[] = 'grade_categories.grade_system_id';
    }
    if (!$hasIndex('grade_categories', 'idx_grade_categories_owner')) {
        $pdo->exec('ALTER TABLE grade_categories ADD KEY idx_grade_categories_owner (owner_tenant_id, grade_system_id)');
    }

    if ($changed !== []) {
        echo 'Grades sur mesure : ' . implode(', ', $changed) . " ajouté(s).\n";
    }
};
