<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_send_queue_snapshots / atak_send_queue_history (files d » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$atakSendQueueMigrate = require $root . '/bootstrap/atak_send_queue_migration.php';
$migrationRunner->step('atak_send_queue', static function () use ($atakSendQueueMigrate, $pdo): void {
    echo "Migration atak_send_queue_snapshots / atak_send_queue_history (files d'envoi vers Athena)...\n";
    $atakSendQueueMigrate($pdo);
});
