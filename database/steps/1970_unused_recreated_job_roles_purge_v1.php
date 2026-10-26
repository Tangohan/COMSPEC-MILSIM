<?php

declare(strict_types=1);

/**
 * Étape « Migration unused_recreated_job_roles_purge_v1 (retrait des emplois revenus et non attribués) » (Maintenance).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$unusedRecreatedJobRolesPurgeMigrate = require $root . '/bootstrap/unused_recreated_job_roles_purge_v1_migration.php';
try {
    echo "Migration unused_recreated_job_roles_purge_v1 (retrait des emplois revenus et non attribués)...\n";
    $unusedRecreatedJobRolesPurgeMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] unused_recreated_job_roles_purge_v1 : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('unused_recreated_job_roles_purge_v1', $e);
}
