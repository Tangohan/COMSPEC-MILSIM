<?php

declare(strict_types=1);

/**
 * Étape « Migration community_media (collections / items) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$communityMediaMigrate = require $root . '/bootstrap/community_media_migration.php';
try {
    echo "Migration community_media (collections / items)...\n";
    $communityMediaMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] community_media : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('community_media', $e);
}
