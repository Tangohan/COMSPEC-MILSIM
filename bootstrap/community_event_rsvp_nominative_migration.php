<?php

declare(strict_types=1);

/**
 * Colonnes complémentaires pour la vue « Réponses nominatives » (disponibilité, relances, commentaire orga).
 */
return static function (PDO $pdo): void {
    $hasColumn = static function (string $table, string $column) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    if (!$hasColumn('community_event_rsvps', 'availability_from')) {
        try {
            $pdo->exec('ALTER TABLE community_event_rsvps ADD COLUMN availability_from TIME DEFAULT NULL AFTER absence_note');
        } catch (\Throwable) {
        }
    }
    if (!$hasColumn('community_event_rsvps', 'availability_to')) {
        try {
            $pdo->exec('ALTER TABLE community_event_rsvps ADD COLUMN availability_to TIME DEFAULT NULL AFTER availability_from');
        } catch (\Throwable) {
        }
    }
    if (!$hasColumn('community_event_rsvps', 'admin_comment')) {
        try {
            $pdo->exec('ALTER TABLE community_event_rsvps ADD COLUMN admin_comment VARCHAR(255) DEFAULT NULL AFTER availability_to');
        } catch (\Throwable) {
        }
    }
    if (!$hasColumn('community_event_rsvps', 'reminder_count')) {
        try {
            $pdo->exec('ALTER TABLE community_event_rsvps ADD COLUMN reminder_count TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER reminder_sent_at');
        } catch (\Throwable) {
        }
    }
};
