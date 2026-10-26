<?php

declare(strict_types=1);

/**
 * Étape « Migration community_event_rsvp_history » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$communityEventRsvpHistoryMigrate = require $root . '/bootstrap/community_event_rsvp_history_migration.php';
try {
    echo "Migration community_event_rsvp_history...\n";
    $communityEventRsvpHistoryMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] community_event_rsvp_history : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('community_event_rsvp_history', $e);
}
