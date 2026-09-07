<?php

declare(strict_types=1);

use App\Services\Personnel\UnitJobRoleSyncService;

/**
 * One-shot : retire le catalogue militaire encore présent s’il n’est posé sur aucun dossier.
 * Reconnaît aussi les copies dont la catégorie (Forces spéciales, Artillerie…) vient du catalogue,
 * pas seulement le code interne.
 */
return static function (PDO $pdo): void {
    $hasTable = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if (!$hasTable('personnel_job_roles')) {
        echo "  unused_military_catalog_jobs_purge_v2 : table absente, rien à faire.\n";

        return;
    }

    $sync = new UnitJobRoleSyncService($pdo);
    $purged = $sync->purgeAllUnusedCatalogJobs();
    echo '  copies catalogue militaire inutilisées retirées : ' . $purged . "\n";
    echo "  les emplois d’unité et ceux déjà posés sur un dossier restent.\n";
};
