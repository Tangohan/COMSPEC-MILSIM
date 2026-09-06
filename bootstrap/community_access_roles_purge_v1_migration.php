<?php

declare(strict_types=1);

use App\Core\Database;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use App\Services\Personnel\UnitJobRoleSyncService;
use App\Services\Rbac\CommunityAccessCollapseService;

/**
 * Purge les copies catalogue dans `roles` (hors Membre / RH / Gestionnaire)
 * et vide les droits collés aux emplois. Idempotent : rejouable après v1
 * qui convertissait les gens sans supprimer les lignes.
 */
return static function (PDO $pdo): void {
    if (!class_exists(CommunityAccessCollapseService::class)) {
        echo "  [ATTENTION] community_access_roles_purge_v1 : classe absente.\n";

        return;
    }

    echo "Purge des copies d’accès communauté (trois profils + positions de service)...\n";
    $service = new CommunityAccessCollapseService(
        Database::getPdo(),
        new RoleRepository(),
        new UserRepository()
    );
    $service->collapseAllTenants();

    $hasTable = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if ($hasTable('personnel_job_role_permissions')) {
        try {
            $n = $pdo->exec('DELETE FROM personnel_job_role_permissions');
            echo '  liaisons emploi → droits vidées : ' . (int) $n . "\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] personnel_job_role_permissions : ' . $e->getMessage() . "\n";
        }
    }

    if ($hasTable('personnel_job_roles')) {
        $sync = new UnitJobRoleSyncService($pdo);
        $purged = $sync->purgeAllUnusedCatalogJobs();
        echo '  emplois catalogue inutilisés retirés : ' . $purged . "\n";
    }

    echo "community_access_roles_purge_v1 : OK.\n";
};
