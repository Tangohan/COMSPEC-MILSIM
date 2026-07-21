<?php

declare(strict_types=1);

/**
 * Slotting de mission : rôles/postes à pourvoir sur un événement, avec capacité et
 * inscription nominative (liste d'attente si complet).
 *
 * @return callable(PDO): void
 */
return static function (PDO $pdo): void {
    $hasTable = static function (PDO $pdo, string $table): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if (!$hasTable($pdo, 'community_events')) {
        return;
    }

    if (!$hasTable($pdo, 'community_event_slots')) {
        try {
            $pdo->exec(
                'CREATE TABLE community_event_slots (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    tenant_id INT UNSIGNED NOT NULL,
                    event_id INT UNSIGNED NOT NULL,
                    label VARCHAR(160) NOT NULL,
                    unit_id INT UNSIGNED DEFAULT NULL,
                    capacity SMALLINT UNSIGNED NOT NULL DEFAULT 1,
                    loadout_notes TEXT DEFAULT NULL,
                    position INT NOT NULL DEFAULT 0,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_slot_event_position (event_id, position),
                    KEY idx_slot_tenant (tenant_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            echo "  [OK] community_event_slots\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] community_event_slots : ' . $e->getMessage() . "\n";

            return;
        }
    }

    if (!$hasTable($pdo, 'community_event_slot_assignments')) {
        try {
            $pdo->exec(
                'CREATE TABLE community_event_slot_assignments (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    tenant_id INT UNSIGNED NOT NULL,
                    slot_id INT UNSIGNED NOT NULL,
                    event_id INT UNSIGNED NOT NULL,
                    user_id INT UNSIGNED NOT NULL,
                    status VARCHAR(16) NOT NULL DEFAULT \'confirmed\',
                    waitlist_position INT UNSIGNED DEFAULT NULL,
                    signed_up_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_slot_assignment_event_user (event_id, user_id),
                    KEY idx_slot_assignment_slot_status (slot_id, status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            echo "  [OK] community_event_slot_assignments\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] community_event_slot_assignments : ' . $e->getMessage() . "\n";
        }
    }
};
