<?php

declare(strict_types=1);

/**
 * Étape « Migration community_events (détails / image / conditions) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$communityEventsDetailsMigrate = require $root . '/bootstrap/community_events_details_migration.php';
try {
    echo "Migration community_events (détails / image / conditions)...\n";
    $communityEventsDetailsMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] community_events_details : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('community_events_details', $e);
}
