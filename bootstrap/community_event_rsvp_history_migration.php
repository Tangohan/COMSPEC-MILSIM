<?php

declare(strict_types=1);

/**
 * Historique des changements de participation / pointage (RSVP).
 *
 * @return callable(PDO): void
 */
return static function (PDO $pdo): void {
    $hasTable = static function (PDO $pdo, string $table): bool {
        $st = $pdo->prepare(
            "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1"
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if (!$hasTable($pdo, 'community_events') || !$hasTable($pdo, 'community_event_rsvps')) {
        return;
    }

    if ($hasTable($pdo, 'community_event_rsvp_history')) {
        return;
    }

    try {
        $pdo->exec(
            "CREATE TABLE community_event_rsvp_history (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                event_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                actor_user_id INT UNSIGNED DEFAULT NULL,
                action VARCHAR(32) NOT NULL,
                status_from VARCHAR(16) DEFAULT NULL,
                status_to VARCHAR(16) DEFAULT NULL,
                absence_reason VARCHAR(64) DEFAULT NULL,
                absence_note VARCHAR(500) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_rsvp_hist_event_user (event_id, user_id, created_at),
                KEY idx_rsvp_hist_user (user_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        echo "  [OK] community_event_rsvp_history\n";
    } catch (Throwable $e) {
        echo '  [ATTENTION] community_event_rsvp_history : ' . $e->getMessage() . "\n";

        return;
    }

    // Amorçage : une entrée initiale par RSVP existant.
    try {
        $hasAbsence = false;
        $stCol = $pdo->query(
            "SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'community_event_rsvps' AND COLUMN_NAME = 'absence_reason' LIMIT 1"
        );
        $hasAbsence = $stCol && (bool) $stCol->fetchColumn();

        if ($hasAbsence) {
            $pdo->exec(
                "INSERT INTO community_event_rsvp_history
                    (event_id, user_id, actor_user_id, action, status_from, status_to, absence_reason, absence_note, created_at)
                 SELECT r.event_id, r.user_id, r.user_id, 'rsvp_set', NULL, r.status,
                        r.absence_reason, r.absence_note,
                        COALESCE(r.created_at, NOW())
                 FROM community_event_rsvps r
                 WHERE NOT EXISTS (
                    SELECT 1 FROM community_event_rsvp_history h
                    WHERE h.event_id = r.event_id AND h.user_id = r.user_id
                 )"
            );
        } else {
            $pdo->exec(
                "INSERT INTO community_event_rsvp_history
                    (event_id, user_id, actor_user_id, action, status_from, status_to, absence_reason, absence_note, created_at)
                 SELECT r.event_id, r.user_id, r.user_id, 'rsvp_set', NULL, r.status,
                        NULL, NULL,
                        COALESCE(r.created_at, NOW())
                 FROM community_event_rsvps r
                 WHERE NOT EXISTS (
                    SELECT 1 FROM community_event_rsvp_history h
                    WHERE h.event_id = r.event_id AND h.user_id = r.user_id
                 )"
            );
        }
        echo "  [OK] community_event_rsvp_history amorcé depuis les RSVP existants\n";
    } catch (Throwable $e) {
        echo '  [ATTENTION] amorçage historique RSVP : ' . $e->getMessage() . "\n";
    }
};
