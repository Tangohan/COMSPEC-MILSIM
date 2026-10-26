<?php

declare(strict_types=1);

/**
 * Étape « Migration user_profile_banner » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$userProfileBannerMigrate = require $root . '/bootstrap/user_profile_banner_migration.php';
try {
    $userProfileBannerMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] user_profile_banner : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('user_profile_banner', $e);
}
