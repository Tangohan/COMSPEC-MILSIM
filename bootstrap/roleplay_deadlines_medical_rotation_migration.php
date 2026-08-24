<?php

declare(strict_types=1);

/**
 * Échéances roleplay : constat de groupe sanguin (bilan médical) et type de rotation.
 * Idempotent. Les dossiers historiques restent NULL (pas de fausse date ni de faux groupe).
 */
return static function (PDO $pdo): void {
    $hasColumn = static function (PDO $pdo, string $table, string $column): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    if (!$hasColumn($pdo, 'personnel_profiles', 'user_id') && !$hasColumn($pdo, 'personnel_profiles', 'id')) {
        return;
    }

    $columns = [
        'rp_last_interview_completed_at' => 'DATETIME NULL DEFAULT NULL',
        'rp_last_rotation_completed_at' => 'DATETIME NULL DEFAULT NULL',
        'rp_rotation_kind' => 'VARCHAR(32) NULL DEFAULT NULL',
        'rp_blood_type_confirmed' => 'VARCHAR(16) NULL DEFAULT NULL',
        'rp_blood_type_confirmed_at' => 'DATETIME NULL DEFAULT NULL',
        'rp_arma_blood_type' => 'VARCHAR(16) NULL DEFAULT NULL',
        'rp_arma_blood_type_at' => 'DATETIME NULL DEFAULT NULL',
    ];

    foreach ($columns as $column => $ddl) {
        if ($hasColumn($pdo, 'personnel_profiles', $column)) {
            continue;
        }
        try {
            $pdo->exec('ALTER TABLE personnel_profiles ADD COLUMN `' . $column . '` ' . $ddl);
        } catch (Throwable $e) {
            echo '  [ATTENTION] roleplay_deadlines_medical_rotation (' . $column . ') : ' . $e->getMessage() . "\n";
        }
    }
};
