<?php

declare(strict_types=1);

/**
 * Étape « Migration tenant_access_profiles_alignment (droits Membre / RH / Gestionnaire par profil) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$accessAlignMigrate = require $root . '/bootstrap/tenant_access_profiles_alignment_migration.php';
try {
    echo "Migration tenant_access_profiles_alignment (droits Membre / RH / Gestionnaire par profil)...\n";
    require_once $root . '/bootstrap/autoload.php';
    $accessAlignMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] tenant_access_profiles_alignment : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('tenant_access_profiles_alignment', $e);
}
$migrationEnsurePdo();
