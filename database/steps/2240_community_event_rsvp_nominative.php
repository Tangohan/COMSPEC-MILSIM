<?php

declare(strict_types=1);

/**
 * Étape « Migration community_event_rsvp_nominative (disponibilité, relances, commentaires orga) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$communityEventRsvpNominativeMigrate = require $root . '/bootstrap/community_event_rsvp_nominative_migration.php';
try {
    echo "Migration community_event_rsvp_nominative (disponibilité, relances, commentaires orga)...\n";
    $migrationFlush();
    $communityEventRsvpNominativeMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] community_event_rsvp_nominative : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('community_event_rsvp_nominative', $e);
}
$migrationEnsurePdo();
