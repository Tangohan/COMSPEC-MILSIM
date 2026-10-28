<?php

declare(strict_types=1);

/**
 * Étape « Migration tenant_login_accueil_images » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$loginAccueilImagesMigrate = require $root . '/bootstrap/tenant_login_accueil_images_migration.php';
try {
    $loginAccueilImagesMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] tenant_login_accueil_images : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('tenant_login_accueil_images', $e);
}
