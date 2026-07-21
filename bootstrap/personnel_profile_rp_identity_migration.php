<?php

declare(strict_types=1);

/**
 * Champs d'identité RP supplémentaires pour la fiche opérateur (sexe, situation familiale,
 * poids, statut opérateur, tags de spécialité). Idempotent.
 */
function run_personnel_profile_rp_identity_migration(PDO $pdo): void
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
        'sex' => "ALTER TABLE personnel_profiles ADD COLUMN sex VARCHAR(20) NULL AFTER nationality",
        'family_situation' => "ALTER TABLE personnel_profiles ADD COLUMN family_situation VARCHAR(100) NULL AFTER sex",
        'weight_kg' => "ALTER TABLE personnel_profiles ADD COLUMN weight_kg SMALLINT UNSIGNED NULL AFTER family_situation",
        'operator_status' => "ALTER TABLE personnel_profiles ADD COLUMN operator_status VARCHAR(160) NULL AFTER weight_kg",
        'operator_tags' => "ALTER TABLE personnel_profiles ADD COLUMN operator_tags VARCHAR(255) NULL AFTER operator_status",
    ];

    foreach ($columns as $column => $sql) {
        if ($columnExists($pdo, 'personnel_profiles', $column)) {
            continue;
        }
        $pdo->exec($sql);
    }
}
