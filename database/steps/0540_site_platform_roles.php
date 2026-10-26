<?php

declare(strict_types=1);

/**
 * Étape « Rôles site de la plateforme » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$sitePlatformRolesPath = $root . '/bootstrap/site_platform_roles_migration.php';
if (is_file($sitePlatformRolesPath)) {
    require_once $sitePlatformRolesPath;
    try {
        run_site_platform_roles_migration($pdo);
    } catch (Throwable $e) {
        echo '  [ATTENTION] site_platform_roles : ' . $e->getMessage() . "\n";
        $migrationRunner->warn('site_platform_roles', $e);
    }
} else {
    echo "  [ATTENTION] Fichier absent : bootstrap/site_platform_roles_migration.php — ajoutez-le sur le serveur (même version que le dépôt) puis relancez pour créer les rôles site (modération, assistance).\n";
}
