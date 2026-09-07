<?php

declare(strict_types=1);

use App\Services\Personnel\UnitJobRoleSyncService;

/**
 * Emplois de dossier : schéma seulement.
 * Ne recrée jamais un référentiel vidé. Un emploi naît à la création d’une unité,
 * à une affectation, ou quand un responsable copie un modèle — pas à chaque migration.
 */
return static function (PDO $pdo): void {
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

    if (!$hasTable('personnel_job_roles')) {
        echo "  unit_derived_job_roles : table absente, rien à faire.\n";

        return;
    }

    if (!$hasColumn('personnel_job_roles', 'source_unit_id')) {
        try {
            $pdo->exec(
                'ALTER TABLE personnel_job_roles
                 ADD COLUMN source_unit_id INT UNSIGNED DEFAULT NULL AFTER category_id'
            );
            echo "  personnel_job_roles.source_unit_id ajouté.\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] source_unit_id : ' . $e->getMessage() . "\n";
        }
        try {
            $pdo->exec('ALTER TABLE personnel_job_roles ADD KEY pjr_source_unit (source_unit_id)');
        } catch (Throwable) {
        }
        if ($hasTable('units')) {
            try {
                $pdo->exec(
                    'ALTER TABLE personnel_job_roles
                     ADD CONSTRAINT pjr_source_unit_fk
                     FOREIGN KEY (source_unit_id) REFERENCES units (id)
                     ON DELETE SET NULL ON UPDATE CASCADE'
                );
            } catch (Throwable) {
            }
        }
        UnitJobRoleSyncService::resetColumnCache();
    }

    if ($hasTable('personnel_job_role_permissions')) {
        try {
            $n = $pdo->exec('DELETE FROM personnel_job_role_permissions');
            echo '  liaisons emploi → droits vidées : ' . (int) $n . "\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] personnel_job_role_permissions : ' . $e->getMessage() . "\n";
        }
    }

    $sync = new UnitJobRoleSyncService($pdo);
    $purged = $sync->purgeAllUnusedCatalogJobs();
    echo '  emplois catalogue inutilisés retirés : ' . $purged . "\n";
    echo "  aucun emploi recréé (le référentiel vidé reste vide).\n";
};
