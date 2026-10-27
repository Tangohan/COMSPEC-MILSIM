<?php

declare(strict_types=1);

/**
 * Étape « Migration community_access_profiles_v1 (trois niveaux d’accès) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$communityAccessProfilesMigrate = require $root . '/bootstrap/community_access_profiles_v1_migration.php';
try {
    echo "Migration community_access_profiles_v1 (trois niveaux d’accès)...\n";
    $communityAccessProfilesMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] community_access_profiles_v1 : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('community_access_profiles_v1', $e);
}
