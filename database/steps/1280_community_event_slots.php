<?php

declare(strict_types=1);

/**
 * Étape « Migration community_event_slots (slotting de mission) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$communityEventSlotsMigrate = require $root . '/bootstrap/community_event_slots_migration.php';
try {
    echo "Migration community_event_slots (slotting de mission)...\n";
    $communityEventSlotsMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] community_event_slots : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('community_event_slots', $e);
}
