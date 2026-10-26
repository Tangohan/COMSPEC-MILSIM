<?php

declare(strict_types=1);

/**
 * Étape « Migration community_event_slot_qualification » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$communityEventSlotQualificationMigrate = require $root . '/bootstrap/community_event_slot_qualification_migration.php';
try {
    $communityEventSlotQualificationMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] community_event_slot_qualification : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('community_event_slot_qualification', $e);
}
