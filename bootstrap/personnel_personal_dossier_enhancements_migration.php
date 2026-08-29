<?php

declare(strict_types=1);

/**
 * Compléments du dossier personnel :
 * - surnoms métier,
 * - placards / décorations déclaratives,
 * - motif lisible sur les changements d'affectation et d'historique.
 *
 * Idempotent.
 */
function run_personnel_personal_dossier_enhancements_migration(PDO $pdo): void
{
    $tableExists = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    $columnExists = static function (string $table, string $column) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    if ($tableExists('personnel_profiles')) {
        $columns = [
            'nickname_primary' => "ALTER TABLE personnel_profiles ADD COLUMN nickname_primary VARCHAR(120) NULL AFTER callsign",
            'nicknames_json' => "ALTER TABLE personnel_profiles ADD COLUMN nicknames_json JSON NULL AFTER nickname_primary",
            'medal_rack_json' => "ALTER TABLE personnel_profiles ADD COLUMN medal_rack_json JSON NULL AFTER nicknames_json",
            'extra_callsigns_json' => "ALTER TABLE personnel_profiles ADD COLUMN extra_callsigns_json JSON NULL COMMENT 'Indicatifs radio supplémentaires (liste)' AFTER callsign",
        ];
        foreach ($columns as $column => $sql) {
            if (!$columnExists('personnel_profiles', $column)) {
                $pdo->exec($sql);
            }
        }
    }

    if ($tableExists('personnel_assignments') && !$columnExists('personnel_assignments', 'change_reason')) {
        $pdo->exec("ALTER TABLE personnel_assignments ADD COLUMN change_reason VARCHAR(255) NULL AFTER role_name");
    }

    if ($tableExists('personnel_qualifications') && !$columnExists('personnel_qualifications', 'reason_label')) {
        $pdo->exec("ALTER TABLE personnel_qualifications ADD COLUMN reason_label VARCHAR(255) NULL AFTER qualification_name");
    }

    if ($tableExists('personnel_service_history') && !$columnExists('personnel_service_history', 'reason_label')) {
        $pdo->exec("ALTER TABLE personnel_service_history ADD COLUMN reason_label VARCHAR(255) NULL AFTER title");
    }
}
