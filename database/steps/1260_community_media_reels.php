<?php

declare(strict_types=1);

/**
 * Étape « Migration community_media reels (feed / social) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$communityMediaReelsMigrate = require $root . '/bootstrap/community_media_reels_migration.php';
try {
    echo "Migration community_media reels (feed / social)...\n";
    $communityMediaReelsMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] community_media_reels : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('community_media_reels', $e);
}
