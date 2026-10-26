<?php

declare(strict_types=1);

/**
 * Étape « Migration community_access_roles_purge_v1 (retrait des copies d’accès) » (Maintenance).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$communityAccessRolesPurgeMigrate = require $root . '/bootstrap/community_access_roles_purge_v1_migration.php';
try {
    echo "Migration community_access_roles_purge_v1 (retrait des copies d’accès)...\n";
    $communityAccessRolesPurgeMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] community_access_roles_purge_v1 : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('community_access_roles_purge_v1', $e);
}
