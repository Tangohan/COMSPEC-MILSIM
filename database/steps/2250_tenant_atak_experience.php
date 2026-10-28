<?php

declare(strict_types=1);

/**
 * Étape « Migration tenant_atak_experience (expérience Overwatch par communauté) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$tenantAtakExperienceMigrate = require $root . '/bootstrap/tenant_atak_experience_migration.php';
try {
    echo "Migration tenant_atak_experience (expérience Overwatch par communauté)...\n";
    $migrationFlush();
    $tenantAtakExperienceMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] tenant_atak_experience : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('tenant_atak_experience', $e);
}
$migrationEnsurePdo();
