<?php

declare(strict_types=1);

/**
 * Étape « Migration community_events (event_type, annulation) » (Schéma).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Pointage / RSVP : colonnes community_events + community_event_rsvps (idempotent si bootstrap déjà passé)
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'community_events' AND COLUMN_NAME = 'cancelled_at'");
if ($stmt && !$stmt->fetch()) {
    echo "Migration community_events (event_type, annulation)...\n";
    try {
        $pdo->exec("ALTER TABLE community_events ADD COLUMN event_type varchar(32) NOT NULL DEFAULT 'evenement' AFTER campaign_tag");
    } catch (Throwable) {
    }
    try {
        $pdo->exec("ALTER TABLE community_events ADD COLUMN cancelled_at datetime DEFAULT NULL AFTER updated_at");
        $pdo->exec("ALTER TABLE community_events ADD COLUMN cancelled_reason varchar(500) DEFAULT NULL AFTER cancelled_at");
    } catch (Throwable $e) {
        echo '  [ATTENTION] community_events annulation : ' . $e->getMessage() . "\n";
        $migrationRunner->warn('community_events annulation', $e);
    }
}
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'community_event_rsvps' AND COLUMN_NAME = 'checked_in_at'");
if ($stmt && !$stmt->fetch()) {
    echo "Migration community_event_rsvps (checked_in_at, reminder_sent_at)...\n";
    try {
        $pdo->exec("ALTER TABLE community_event_rsvps ADD COLUMN checked_in_at datetime DEFAULT NULL AFTER status");
        $pdo->exec("ALTER TABLE community_event_rsvps ADD COLUMN reminder_sent_at datetime DEFAULT NULL AFTER checked_in_at");
        $pdo->exec('ALTER TABLE community_event_rsvps ADD KEY idx_rsvp_reminder (event_id, reminder_sent_at)');
    } catch (Throwable $e) {
        echo '  [ATTENTION] community_event_rsvps pointage : ' . $e->getMessage() . "\n";
        $migrationRunner->warn('community_event_rsvps pointage', $e);
    }
}
