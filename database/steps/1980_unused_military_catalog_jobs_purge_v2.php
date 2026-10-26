<?php

declare(strict_types=1);

/**
 * Étape « Migration unused_military_catalog_jobs_purge_v2 (retrait du catalogue militaire inutilisé) » (Maintenance).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$unusedMilitaryCatalogJobsPurgeV2 = require $root . '/bootstrap/unused_military_catalog_jobs_purge_v2_migration.php';
try {
    echo "Migration unused_military_catalog_jobs_purge_v2 (retrait du catalogue militaire inutilisé)...\n";
    $unusedMilitaryCatalogJobsPurgeV2($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] unused_military_catalog_jobs_purge_v2 : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('unused_military_catalog_jobs_purge_v2', $e);
}
