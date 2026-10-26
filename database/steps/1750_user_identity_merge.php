<?php

declare(strict_types=1);

/**
 * Étape « Fusion des comptes partageant le même e-mail (une identité, dossiers RH séparés) » (Maintenance).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

try {
    echo "Fusion des comptes partageant le même e-mail (une identité, dossiers RH séparés)...\n";
    $merge = new \App\Services\Identity\UserIdentityMergeService(
        $pdo,
        new \App\Repositories\UserCommunityMembershipRepository($pdo)
    );
    $mergeSummary = $merge->mergeAllDuplicateEmails();
    echo '  groupes=' . (int) ($mergeSummary['groups'] ?? 0)
        . ' fiches réunies=' . (int) ($mergeSummary['merged'] ?? 0)
        . ' collisions Steam=' . (int) ($mergeSummary['collisions'] ?? 0) . "\n";
    foreach ($mergeSummary['errors'] ?? [] as $err) {
        echo '  [ATTENTION] fusion : ' . $err . "\n";
    }
    echo "Reprise des dossiers laissés sur les comptes réunis...\n";
    $restore = new \App\Services\Identity\UserIdentityProfileRestoreService($pdo);
    $restored = $restore->restoreAll();
    echo '  fusions=' . (int) ($restored['merges'] ?? 0)
        . ' dossiers RH=' . (int) ($restored['personnel'] ?? 0)
        . ' fiches civiles=' . (int) ($restored['user_profiles'] ?? 0)
        . ' fiches communauté=' . (int) ($restored['community_profiles'] ?? 0) . "\n";
} catch (Throwable $e) {
    echo '  [ATTENTION] user_identity_merge : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('user_identity_merge', $e);
}
