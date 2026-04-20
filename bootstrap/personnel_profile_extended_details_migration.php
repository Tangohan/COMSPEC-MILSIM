<?php

declare(strict_types=1);

/**
 * Champs supplémentaires de fiche militaire pour enrichir les dossiers personnels.
 * Idempotent.
 */
function run_personnel_profile_extended_details_migration(PDO $pdo): void
{
    $tableExists = static function (PDO $pdo, string $table): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    $columnExists = static function (PDO $pdo, string $table, string $column): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    if (!$tableExists($pdo, 'personnel_profiles')) {
        return;
    }

    $columns = [
        'birth_place' => "ALTER TABLE personnel_profiles ADD COLUMN birth_place VARCHAR(150) NULL AFTER nationality",
        'service_branch' => "ALTER TABLE personnel_profiles ADD COLUMN service_branch VARCHAR(120) NULL AFTER birth_place",
        'service_status' => "ALTER TABLE personnel_profiles ADD COLUMN service_status VARCHAR(120) NULL AFTER service_branch",
        'gendarmerie_status' => "ALTER TABLE personnel_profiles ADD COLUMN gendarmerie_status VARCHAR(120) NULL AFTER service_status",
        'administrative_position' => "ALTER TABLE personnel_profiles ADD COLUMN administrative_position VARCHAR(120) NULL AFTER gendarmerie_status",
        'bureau_sn' => "ALTER TABLE personnel_profiles ADD COLUMN bureau_sn VARCHAR(120) NULL AFTER administrative_position",
        'military_origin' => "ALTER TABLE personnel_profiles ADD COLUMN military_origin VARCHAR(120) NULL AFTER bureau_sn",
        'statutory_limit_date' => "ALTER TABLE personnel_profiles ADD COLUMN statutory_limit_date DATE NULL AFTER military_origin",
        'management_service_limit_date' => "ALTER TABLE personnel_profiles ADD COLUMN management_service_limit_date DATE NULL AFTER statutory_limit_date",
    ];

    foreach ($columns as $column => $sql) {
        if ($columnExists($pdo, 'personnel_profiles', $column)) {
            continue;
        }
        $pdo->exec($sql);
    }
}
