<?php

declare(strict_types=1);

/**
 * Étape « Migration military_role_catalog_schema » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

try {
    run_military_role_catalog_schema_migration($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] military_role_catalog_schema : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('military_role_catalog_schema', $e);
}
