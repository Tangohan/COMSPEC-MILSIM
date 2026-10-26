<?php

declare(strict_types=1);

/**
 * Étape « Migration cooperation_announcement_events_v3 » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$cooperationAnnouncementsV3 = require $root . '/bootstrap/cooperation_announcement_events_v3_migration.php';
try {
    $cooperationAnnouncementsV3($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] cooperation_announcement_events_v3 : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('cooperation_announcement_events_v3', $e);
}
