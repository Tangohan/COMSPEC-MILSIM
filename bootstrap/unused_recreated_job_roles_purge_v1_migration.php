<?php

declare(strict_types=1);

use App\Services\Personnel\UnitJobRoleSyncService;

/**
 * One-shot idempotent : retire les emplois revenus tout seuls et non attribués.
 * Catalogue militaire inutilisé + emplois d’unité (slug unit-% / source_unit_id).
 * Ne recrée rien. Ne touche pas à un emploi encore posé sur un dossier.
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
        echo "  unused_recreated_job_roles_purge_v1 : table absente, rien à faire.\n";

        return;
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
    $purged = $sync->purgeAllUnusedAutoCreatedJobs();
    echo '  emplois auto-créés inutilisés retirés : ' . $purged . "\n";
    echo "  les emplois déjà posés sur un dossier restent.\n";
};
